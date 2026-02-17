terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

locals {
  name = "${var.project}-${var.stack_name}-${var.environment}"
  common_tags = {
    Project     = var.project
    Environment = var.environment
    Stack       = var.stack_name
    ManagedBy   = "terraform"
  }
}

# ── A.14: ECR repository with scan on push and immutable tags ─────────────────
# Immutable tags prevent overwriting released images.
# Scan on push detects known CVEs in container images before deployment.

resource "aws_ecr_repository" "this" {
  name                 = local.name
  image_tag_mutability = var.image_tag_mutability

  # A.14: Scan every image pushed to catch known vulnerabilities
  image_scanning_configuration {
    scan_on_push = true
  }

  # A.10: Encrypt images at rest using the default AWS-managed key
  encryption_configuration {
    encryption_type = "AES256"
  }

  tags = local.common_tags
}

# Keep only the last N images to control storage cost while retaining
# enough history for rollbacks.
resource "aws_ecr_lifecycle_policy" "this" {
  repository = aws_ecr_repository.this.name

  policy = jsonencode({
    rules = [
      {
        rulePriority = 1
        description  = "Keep last ${var.max_image_count} tagged images"
        selection = {
          tagStatus     = "tagged"
          tagPrefixList = ["v"]
          countType     = "imageCountMoreThan"
          countNumber   = var.max_image_count
        }
        action = {
          type = "expire"
        }
      },
      {
        rulePriority = 2
        description  = "Expire untagged images older than 7 days"
        selection = {
          tagStatus   = "untagged"
          countType   = "sinceImagePushed"
          countUnit   = "days"
          countNumber = 7
        }
        action = {
          type = "expire"
        }
      }
    ]
  })
}
