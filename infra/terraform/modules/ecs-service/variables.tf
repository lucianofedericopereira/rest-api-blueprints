variable "project" {
  description = "Project name."
  type        = string
}

variable "environment" {
  description = "Deployment environment."
  type        = string
}

variable "stack_name" {
  description = "Stack identifier (e.g. fastapi, nestjs, gin)."
  type        = string
}

variable "aws_region" {
  description = "AWS region."
  type        = string
}

# ── Cluster ───────────────────────────────────────────────────────────────────

variable "cluster_id" {
  description = "ID of the ECS cluster to deploy the service into."
  type        = string
}

variable "cluster_name" {
  description = "Name of the ECS cluster (used for log group naming)."
  type        = string
}

# ── Image ─────────────────────────────────────────────────────────────────────

variable "ecr_repository_url" {
  description = "ECR repository URL (without tag)."
  type        = string
}

variable "image_tag" {
  description = "Container image tag to deploy."
  type        = string
  default     = "latest"
}

# ── Secrets ───────────────────────────────────────────────────────────────────

variable "secret_arn" {
  description = "ARN of the Secrets Manager secret for this stack. Injected into the container at runtime."
  type        = string
}

# ── Container ─────────────────────────────────────────────────────────────────

variable "container_port" {
  description = "Port the application listens on inside the container."
  type        = number
}

variable "cpu" {
  description = "CPU units for the Fargate task (256, 512, 1024, 2048, 4096)."
  type        = number
  default     = 256
}

variable "memory" {
  description = "Memory (MiB) for the Fargate task."
  type        = number
  default     = 512
}

variable "desired_count" {
  description = "Number of task replicas to run."
  type        = number
  default     = 1
}

# Non-secret environment variables (e.g. STACK_NAME, LOG_LEVEL).
# Secrets come from Secrets Manager via the secrets: block — not here.
variable "env_vars" {
  description = "Non-secret environment variables to inject into the container."
  type        = map(string)
  default     = {}
}

# ── Networking ────────────────────────────────────────────────────────────────

variable "subnet_ids" {
  description = "Private subnet IDs for ECS tasks."
  type        = list(string)
}

variable "security_group_ids" {
  description = "Security group IDs to attach to ECS tasks."
  type        = list(string)
}

# ── Load Balancer ─────────────────────────────────────────────────────────────

variable "vpc_id" {
  description = "VPC ID (needed to create the target group)."
  type        = string
}

variable "alb_listener_arn" {
  description = "ARN of the ALB HTTPS listener to attach the listener rule to."
  type        = string
}

variable "host_header" {
  description = "Host header value for ALB routing (e.g. fastapi.api.example.com)."
  type        = string
}

variable "health_check_path" {
  description = "Path for ALB health checks."
  type        = string
  default     = "/api/v1/health/live"
}

# ── Logging ───────────────────────────────────────────────────────────────────

variable "log_retention_days" {
  description = "CloudWatch log group retention in days (A.12: audit logs ≥ 365 days)."
  type        = number
  default     = 365
}
