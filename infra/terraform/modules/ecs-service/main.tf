terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

locals {
  name      = "${var.project}-${var.stack_name}-${var.environment}"
  log_group = "/ecs/${var.cluster_name}/${var.stack_name}"
  image     = "${var.ecr_repository_url}:${var.image_tag}"

  common_tags = {
    Project     = var.project
    Environment = var.environment
    Stack       = var.stack_name
    ManagedBy   = "terraform"
  }
}

# ── A.12: CloudWatch log group — 365-day retention ────────────────────────────
# One log group per stack. All application output (stdout/stderr) goes here.

resource "aws_cloudwatch_log_group" "app" {
  name              = local.log_group
  retention_in_days = var.log_retention_days

  tags = local.common_tags
}

# ── A.9: IAM — task execution role (least privilege) ─────────────────────────
# This role is assumed by the ECS agent (not the application code).
# It needs: pull images from ECR, write logs to CloudWatch, read secrets.

resource "aws_iam_role" "task_execution" {
  name = "${local.name}-exec"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect    = "Allow"
        Principal = { Service = "ecs-tasks.amazonaws.com" }
        Action    = "sts:AssumeRole"
      }
    ]
  })

  tags = local.common_tags
}

# Attach the AWS-managed ECS task execution policy (ECR pull + CloudWatch basic)
resource "aws_iam_role_policy_attachment" "task_execution_managed" {
  role       = aws_iam_role.task_execution.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

# A.9: Least-privilege — only allow reading THIS stack's secret
resource "aws_iam_role_policy" "read_secret" {
  name = "${local.name}-read-secret"
  role = aws_iam_role.task_execution.id

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid    = "ReadStackSecret"
        Effect = "Allow"
        Action = [
          "secretsmanager:GetSecretValue",
          "secretsmanager:DescribeSecret"
        ]
        Resource = var.secret_arn
      }
    ]
  })
}

# ── A.9: IAM — task role (what the application process can call) ──────────────
# Separate from the execution role. Application has no AWS permissions by default.
# Add statements here if the app needs to call AWS services (e.g., S3, SQS).

resource "aws_iam_role" "task" {
  name = "${local.name}-task"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect    = "Allow"
        Principal = { Service = "ecs-tasks.amazonaws.com" }
        Action    = "sts:AssumeRole"
      }
    ]
  })

  tags = local.common_tags
}

# ── ALB target group ──────────────────────────────────────────────────────────

resource "aws_lb_target_group" "app" {
  name        = "${local.name}-tg"
  port        = var.container_port
  protocol    = "HTTP"
  vpc_id      = var.vpc_id
  target_type = "ip" # required for Fargate

  health_check {
    path                = var.health_check_path
    protocol            = "HTTP"
    healthy_threshold   = 2
    unhealthy_threshold = 3
    timeout             = 5
    interval            = 30
    matcher             = "200"
  }

  tags = local.common_tags

  lifecycle {
    create_before_destroy = true
  }
}

# Route requests for this stack's host header to its target group
resource "aws_lb_listener_rule" "app" {
  listener_arn = var.alb_listener_arn
  priority     = null # auto-assigned

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.app.arn
  }

  condition {
    host_header {
      values = [var.host_header]
    }
  }

  tags = local.common_tags
}

# ── ECS task definition ───────────────────────────────────────────────────────
# A.10: Secrets are injected via the secrets: block (not environment: block).
# The ECS agent retrieves the secret value from Secrets Manager at task launch.
# The plaintext value is passed into the container's environment — it is NOT
# visible in the task definition JSON stored in AWS (only the ARN is stored).

resource "aws_ecs_task_definition" "app" {
  family                   = local.name
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = var.cpu
  memory                   = var.memory
  execution_role_arn       = aws_iam_role.task_execution.arn
  task_role_arn            = aws_iam_role.task.arn

  container_definitions = jsonencode([
    {
      name      = var.stack_name
      image     = local.image
      essential = true

      portMappings = [
        {
          containerPort = var.container_port
          protocol      = "tcp"
        }
      ]

      # Non-secret env vars (e.g. STACK_NAME, LOG_LEVEL, ENV)
      environment = [
        for k, v in var.env_vars : { name = k, value = v }
      ]

      # A.10: All secrets pulled from Secrets Manager at runtime.
      # The entire JSON blob is surfaced as individual env vars using the
      # `valueFrom` ARN with a JSON key suffix: <secret_arn>:<key>::
      secrets = [
        {
          name      = "APP_SECRETS_JSON"
          valueFrom = var.secret_arn
        }
      ]

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = aws_cloudwatch_log_group.app.name
          "awslogs-region"        = var.aws_region
          "awslogs-stream-prefix" = var.stack_name
        }
      }

      # A.17: Containers that exit unexpectedly are restarted by ECS.
      # Crash loops are surfaced via CloudWatch alarms (not configured here).
      healthCheck = {
        command     = ["CMD-SHELL", "curl -sf http://localhost:${var.container_port}/api/v1/health/live || exit 1"]
        interval    = 30
        timeout     = 5
        retries     = 3
        startPeriod = 60
      }
    }
  ])

  tags = local.common_tags
}

# ── ECS service ───────────────────────────────────────────────────────────────

resource "aws_ecs_service" "app" {
  name            = local.name
  cluster         = var.cluster_id
  task_definition = aws_ecs_task_definition.app.arn
  desired_count   = var.desired_count
  launch_type     = "FARGATE"

  # Allow in-place updates without replacement when the task def changes
  force_new_deployment               = true
  deployment_minimum_healthy_percent = 50
  deployment_maximum_percent         = 200

  network_configuration {
    subnets          = var.subnet_ids
    security_groups  = var.security_group_ids
    assign_public_ip = false # tasks live in private subnets
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.app.arn
    container_name   = var.stack_name
    container_port   = var.container_port
  }

  depends_on = [
    aws_iam_role_policy_attachment.task_execution_managed,
    aws_iam_role_policy.read_secret,
    aws_lb_listener_rule.app,
  ]

  tags = local.common_tags

  lifecycle {
    ignore_changes = [
      # Allow external CI/CD to update the image tag without Terraform drift
      task_definition,
      desired_count,
    ]
  }
}
