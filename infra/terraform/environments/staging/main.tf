terraform {
  required_version = ">= 1.8"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.0"
    }
  }

  # Replace with your S3 backend configuration before applying.
  # backend "s3" {
  #   bucket         = "your-terraform-state-bucket"
  #   key            = "rest-blueprints/staging/terraform.tfstate"
  #   region         = "us-east-1"
  #   dynamodb_table = "terraform-locks"
  #   encrypt        = true
  # }
}

provider "aws" {
  region = var.aws_region

  default_tags {
    tags = {
      Project     = var.project
      Environment = var.environment
      ManagedBy   = "terraform"
    }
  }
}

# ── Locals ────────────────────────────────────────────────────────────────────

locals {
  # Container ports per stack (matches docker-compose / application config)
  stack_ports = {
    fastapi    = 8000
    nestjs     = 3000
    springboot = 8080
    gin        = 8003
    phoenix    = 8004
    symfony    = 9000
    laravel    = 9000
  }

  # Secret maps per stack, keyed by stack name for iteration
  stack_secrets = {
    fastapi    = var.fastapi_secrets
    nestjs     = var.nestjs_secrets
    springboot = var.springboot_secrets
    gin        = var.gin_secrets
    phoenix    = var.phoenix_secrets
    symfony    = var.symfony_secrets
    laravel    = var.laravel_secrets
  }
}

# ── 1. Shared VPC ─────────────────────────────────────────────────────────────

module "vpc" {
  source = "../../modules/vpc"

  project     = var.project
  environment = var.environment
  aws_region  = var.aws_region
}

# ── 2. Shared RDS (PostgreSQL) ────────────────────────────────────────────────
# One RDS instance; each stack uses a separate database within it.

module "rds" {
  source = "../../modules/rds"

  project     = var.project
  environment = var.environment
  identifier  = "${var.project}-${var.environment}"

  vpc_id            = module.vpc.vpc_id
  subnet_ids        = module.vpc.private_subnet_ids
  security_group_id = module.vpc.rds_sg_id

  db_name           = "app" # default DB; stacks get their own via DATABASE_URL
  instance_class    = "db.t3.micro"
  allocated_storage = 20
}

# ── 3. Shared ElastiCache (Redis) ─────────────────────────────────────────────

module "elasticache" {
  source = "../../modules/elasticache"

  project     = var.project
  environment = var.environment

  subnet_ids        = module.vpc.private_subnet_ids
  vpc_id            = module.vpc.vpc_id
  security_group_id = module.vpc.redis_sg_id

  node_type          = "cache.t3.micro"
  num_cache_clusters = 1 # single-node for staging; set 2+ for production
}

# ── 4. ECS Cluster (shared by all stacks) ────────────────────────────────────

resource "aws_ecs_cluster" "main" {
  name = "${var.project}-${var.environment}"

  setting {
    name  = "containerInsights"
    value = "enabled"
  }

  tags = {
    Project     = var.project
    Environment = var.environment
    ManagedBy   = "terraform"
  }
}

resource "aws_ecs_cluster_capacity_providers" "main" {
  cluster_name       = aws_ecs_cluster.main.name
  capacity_providers = ["FARGATE", "FARGATE_SPOT"]

  default_capacity_provider_strategy {
    capacity_provider = "FARGATE"
    weight            = 1
  }
}

# ── 5. Application Load Balancer (shared) ─────────────────────────────────────

resource "aws_lb" "main" {
  name               = "${var.project}-${var.environment}-alb"
  internal           = false
  load_balancer_type = "application"
  security_groups    = [module.vpc.alb_sg_id]
  subnets            = module.vpc.public_subnet_ids

  # A.10: Access logs written to S3 (configure bucket separately)
  # access_logs {
  #   bucket  = "your-alb-logs-bucket"
  #   prefix  = "${var.project}/${var.environment}"
  #   enabled = true
  # }

  tags = {
    Project     = var.project
    Environment = var.environment
    ManagedBy   = "terraform"
  }
}

# Redirect HTTP → HTTPS
resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.main.arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type = "redirect"
    redirect {
      port        = "443"
      protocol    = "HTTPS"
      status_code = "HTTP_301"
    }
  }
}

# HTTPS listener — all stack rules hang off this
resource "aws_lb_listener" "https" {
  load_balancer_arn = aws_lb.main.arn
  port              = 443
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS13-1-2-2021-06"
  certificate_arn   = var.certificate_arn

  # 404 default action if no rule matches
  default_action {
    type = "fixed-response"
    fixed_response {
      content_type = "application/json"
      message_body = "{\"error\":\"not_found\"}"
      status_code  = "404"
    }
  }
}

# ── 6. Per-stack Secrets Manager secrets ─────────────────────────────────────

module "secrets" {
  source   = "../../modules/secrets"
  for_each = local.stack_secrets

  project     = var.project
  environment = var.environment
  stack_name  = each.key

  secret_values        = each.value
  recovery_window_days = 7
}

# ── 7. Per-stack ECR repositories ─────────────────────────────────────────────

module "ecr" {
  source   = "../../modules/ecr"
  for_each = local.stack_ports

  project     = var.project
  environment = var.environment
  stack_name  = each.key

  image_tag_mutability = "IMMUTABLE"
  max_image_count      = 10
}

# ── 8. Per-stack ECS services ─────────────────────────────────────────────────

module "ecs_service" {
  source   = "../../modules/ecs-service"
  for_each = local.stack_ports

  project     = var.project
  environment = var.environment
  stack_name  = each.key
  aws_region  = var.aws_region

  cluster_id   = aws_ecs_cluster.main.id
  cluster_name = aws_ecs_cluster.main.name

  ecr_repository_url = module.ecr[each.key].repository_url
  image_tag          = var.image_tag

  secret_arn = module.secrets[each.key].secret_arn

  container_port = each.value
  cpu            = 256
  memory         = 512
  desired_count  = 1

  env_vars = {
    STACK_NAME  = each.key
    ENVIRONMENT = var.environment
    LOG_LEVEL   = "info"
  }

  subnet_ids         = module.vpc.private_subnet_ids
  security_group_ids = [module.vpc.ecs_sg_id]

  vpc_id           = module.vpc.vpc_id
  alb_listener_arn = aws_lb_listener.https.arn
  host_header      = "${each.key}.${var.domain}"

  health_check_path  = "/api/v1/health/live"
  log_retention_days = 365

  depends_on = [
    module.vpc,
    module.secrets,
    module.ecr,
    aws_ecs_cluster.main,
    aws_lb_listener.https,
  ]
}
