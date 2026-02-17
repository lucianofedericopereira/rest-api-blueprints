terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

locals {
  name = "${var.project}-${var.environment}-redis"
  common_tags = {
    Project     = var.project
    Environment = var.environment
    ManagedBy   = "terraform"
  }
}

# ── Auth token in Secrets Manager (A.10) ──────────────────────────────────────

resource "aws_secretsmanager_secret" "auth_token" {
  name                    = "${local.name}/auth-token"
  description             = "ElastiCache Redis auth token for ${local.name}"
  recovery_window_in_days = 7

  tags = local.common_tags
}

resource "random_password" "auth_token" {
  length  = 32
  special = false # Redis auth tokens must be alphanumeric + select special chars
}

resource "aws_secretsmanager_secret_version" "auth_token" {
  secret_id     = aws_secretsmanager_secret.auth_token.id
  secret_string = jsonencode({ auth_token = random_password.auth_token.result })
}

# ── Subnet group ──────────────────────────────────────────────────────────────

resource "aws_elasticache_subnet_group" "this" {
  name       = "${local.name}-subnet-group"
  subnet_ids = var.subnet_ids

  tags = merge(local.common_tags, { Name = "${local.name}-subnet-group" })
}

# ── Replication group (enables failover — A.17) ───────────────────────────────

resource "aws_elasticache_replication_group" "this" {
  replication_group_id = local.name
  description          = "Redis for ${var.project} ${var.environment}"

  node_type          = var.node_type
  engine_version     = var.engine_version
  port               = 6379
  num_cache_clusters = var.num_cache_clusters

  subnet_group_name  = aws_elasticache_subnet_group.this.name
  security_group_ids = [var.security_group_id]

  # A.10: encryption at rest and in transit
  at_rest_encryption_enabled = true
  transit_encryption_enabled = true
  auth_token                 = random_password.auth_token.result

  # A.17: automatic failover (requires num_cache_clusters >= 2)
  automatic_failover_enabled = var.num_cache_clusters >= 2

  apply_immediately = true

  tags = merge(local.common_tags, { Name = local.name })
}
