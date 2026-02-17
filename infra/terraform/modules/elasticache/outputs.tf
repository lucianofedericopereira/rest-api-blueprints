output "primary_endpoint" {
  description = "Primary endpoint address for Redis."
  value       = aws_elasticache_replication_group.this.primary_endpoint_address
}

output "port" {
  description = "Redis port."
  value       = 6379
}

output "auth_token_secret_arn" {
  description = "ARN of the Secrets Manager secret containing the Redis auth token."
  value       = aws_secretsmanager_secret.auth_token.arn
}

output "redis_url_template" {
  description = "Redis URL template (auth token injected at runtime from Secrets Manager)."
  value       = "rediss://:AUTH_TOKEN@${aws_elasticache_replication_group.this.primary_endpoint_address}:6379/0"
}
