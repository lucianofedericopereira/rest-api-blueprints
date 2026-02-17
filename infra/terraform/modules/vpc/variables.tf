variable "project" {
  description = "Project name used in resource naming and tagging."
  type        = string
}

variable "environment" {
  description = "Deployment environment (staging, production)."
  type        = string
}

variable "aws_region" {
  description = "AWS region to deploy into."
  type        = string
}

variable "vpc_cidr" {
  description = "CIDR block for the VPC."
  type        = string
  default     = "10.0.0.0/16"
}

variable "public_subnet_cidrs" {
  description = "CIDRs for the two public subnets (ALB)."
  type        = list(string)
  default     = ["10.0.0.0/24", "10.0.1.0/24"]
}

variable "private_subnet_cidrs" {
  description = "CIDRs for the two private subnets (ECS, RDS, Redis)."
  type        = list(string)
  default     = ["10.0.10.0/24", "10.0.11.0/24"]
}
