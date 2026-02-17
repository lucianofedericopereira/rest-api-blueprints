variable "project" {
  description = "Project name used as a prefix for all resources."
  type        = string
  default     = "rest-blueprints"
}

variable "aws_region" {
  description = "AWS region to deploy into."
  type        = string
  default     = "us-east-1"
}

variable "environment" {
  description = "Deployment environment identifier."
  type        = string
  default     = "staging"
}

variable "domain" {
  description = "Base domain for per-stack host-header routing (e.g. api.example.com). Each stack gets {stack}.{domain}."
  type        = string
}

variable "certificate_arn" {
  description = "ACM certificate ARN for the ALB HTTPS listener. Must cover *.{domain}."
  type        = string
}

variable "image_tag" {
  description = "Container image tag to deploy for all stacks."
  type        = string
  default     = "latest"
}

# ── Per-stack secret values ───────────────────────────────────────────────────
# Provide these via a terraform.tfvars file (gitignored) or via a secrets
# management backend. Never commit real values.

variable "fastapi_secrets" {
  description = "Secret key/value pairs for the FastAPI stack."
  type        = map(string)
  sensitive   = true
}

variable "nestjs_secrets" {
  description = "Secret key/value pairs for the NestJS stack."
  type        = map(string)
  sensitive   = true
}

variable "springboot_secrets" {
  description = "Secret key/value pairs for the Spring Boot stack."
  type        = map(string)
  sensitive   = true
}

variable "gin_secrets" {
  description = "Secret key/value pairs for the Go/Gin stack."
  type        = map(string)
  sensitive   = true
}

variable "phoenix_secrets" {
  description = "Secret key/value pairs for the Elixir/Phoenix stack."
  type        = map(string)
  sensitive   = true
}

variable "symfony_secrets" {
  description = "Secret key/value pairs for the Symfony stack."
  type        = map(string)
  sensitive   = true
}

variable "laravel_secrets" {
  description = "Secret key/value pairs for the Laravel stack."
  type        = map(string)
  sensitive   = true
}
