output "alb_dns_name" {
  description = "DNS name of the shared Application Load Balancer."
  value       = aws_lb.main.dns_name
}

output "alb_zone_id" {
  description = "Route 53 hosted zone ID of the ALB (for alias records)."
  value       = aws_lb.main.zone_id
}

output "ecs_cluster_name" {
  description = "Name of the shared ECS cluster."
  value       = aws_ecs_cluster.main.name
}

output "vpc_id" {
  description = "VPC ID."
  value       = module.vpc.vpc_id
}

output "rds_endpoint" {
  description = "RDS PostgreSQL endpoint."
  value       = module.rds.endpoint
}

output "rds_secret_arn" {
  description = "ARN of the Secrets Manager secret holding the RDS master password."
  value       = module.rds.password_secret_arn
  sensitive   = true
}

output "redis_endpoint" {
  description = "ElastiCache Redis primary endpoint."
  value       = module.elasticache.primary_endpoint
}

output "redis_auth_token_secret_arn" {
  description = "ARN of the Secrets Manager secret holding the Redis auth token."
  value       = module.elasticache.auth_token_secret_arn
  sensitive   = true
}

# Per-stack outputs (ECR URLs, secret ARNs, log groups)
output "stack_ecr_urls" {
  description = "Map of stack name → ECR repository URL."
  value       = { for k, v in module.ecr : k => v.repository_url }
}

output "stack_secret_arns" {
  description = "Map of stack name → Secrets Manager secret ARN."
  value       = { for k, v in module.secrets : k => v.secret_arn }
  sensitive   = true
}

output "stack_log_groups" {
  description = "Map of stack name → CloudWatch log group name."
  value       = { for k, v in module.ecs_service : k => v.log_group_name }
}

output "stack_service_names" {
  description = "Map of stack name → ECS service name."
  value       = { for k, v in module.ecs_service : k => v.service_name }
}
