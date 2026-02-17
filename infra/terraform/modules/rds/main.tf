terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

locals {
  name = "${var.project}-${var.environment}-${var.identifier}"
  common_tags = {
    Project     = var.project
    Environment = var.environment
    ManagedBy   = "terraform"
  }
}

# ── Master password in Secrets Manager (A.10: credentials never in tfvars) ───

resource "aws_secretsmanager_secret" "db_password" {
  name                    = "${local.name}/db-master-password"
  description             = "RDS master password for ${local.name}"
  recovery_window_in_days = 7

  tags = local.common_tags
}

resource "aws_secretsmanager_secret_version" "db_password" {
  secret_id     = aws_secretsmanager_secret.db_password.id
  secret_string = jsonencode({ password = random_password.master.result })
}

resource "random_password" "master" {
  length           = 32
  special          = true
  override_special = "!#$%&*()-_=+[]{}<>:?"
}

# ── Subnet group ──────────────────────────────────────────────────────────────

resource "aws_db_subnet_group" "this" {
  name       = "${local.name}-db-subnet-group"
  subnet_ids = var.subnet_ids

  tags = merge(local.common_tags, { Name = "${local.name}-db-subnet-group" })
}

# ── RDS PostgreSQL instance ───────────────────────────────────────────────────

resource "aws_db_instance" "this" {
  identifier        = local.name
  engine            = "postgres"
  engine_version    = var.engine_version
  instance_class    = var.instance_class
  allocated_storage = var.allocated_storage

  db_name  = var.db_name
  username = var.db_username
  password = random_password.master.result

  db_subnet_group_name   = aws_db_subnet_group.this.name
  vpc_security_group_ids = [var.security_group_id]

  # A.10: encryption at rest with AWS-managed KMS key
  storage_encrypted = true

  # A.17: automated backups retained 7 days
  backup_retention_period = var.backup_retention_days
  backup_window           = "03:00-04:00"
  maintenance_window      = "Mon:04:00-Mon:05:00"

  # A.17: protect against accidental deletion
  deletion_protection       = true
  skip_final_snapshot       = false
  final_snapshot_identifier = "${local.name}-final-snapshot"

  # A.14: disable public access — private subnet only
  publicly_accessible = false

  # Performance Insights for operational monitoring
  performance_insights_enabled = true

  tags = merge(local.common_tags, { Name = local.name })
}
