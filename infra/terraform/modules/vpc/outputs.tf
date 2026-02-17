output "vpc_id" {
  description = "The VPC ID."
  value       = aws_vpc.this.id
}

output "public_subnet_ids" {
  description = "IDs of the two public subnets (ALB)."
  value       = aws_subnet.public[*].id
}

output "private_subnet_ids" {
  description = "IDs of the two private subnets (ECS, RDS, Redis)."
  value       = aws_subnet.private[*].id
}

output "alb_sg_id" {
  description = "Security group ID for the Application Load Balancer."
  value       = aws_security_group.alb.id
}

output "ecs_sg_id" {
  description = "Security group ID for ECS tasks."
  value       = aws_security_group.ecs.id
}

output "rds_sg_id" {
  description = "Security group ID for RDS PostgreSQL."
  value       = aws_security_group.rds.id
}

output "redis_sg_id" {
  description = "Security group ID for ElastiCache Redis."
  value       = aws_security_group.redis.id
}
