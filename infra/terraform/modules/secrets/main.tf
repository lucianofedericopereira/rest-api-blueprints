terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

locals {
  name = "${var.project}/${var.environment}/${var.stack_name}"
  common_tags = {
    Project     = var.project
    Environment = var.environment
    Stack       = var.stack_name
    ManagedBy   = "terraform"
  }
}

# ── A.10: All application secrets stored in Secrets Manager ───────────────────
# Secrets injected into ECS task definitions at runtime via secrets: [] block.
# They are NEVER stored in environment variables or tfvars files.

resource "aws_secretsmanager_secret" "app" {
  name                    = local.name
  description             = "Application secrets for ${var.stack_name} (${var.environment})"
  recovery_window_in_days = var.recovery_window_days

  tags = local.common_tags
}

resource "aws_secretsmanager_secret_version" "app" {
  secret_id     = aws_secretsmanager_secret.app.id
  secret_string = jsonencode(var.secret_values)
}
