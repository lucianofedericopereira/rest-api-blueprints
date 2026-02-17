output "endpoint" {
  description = "RDS instance endpoint (host:port)."
  value       = aws_db_instance.this.endpoint
}

output "host" {
  description = "RDS instance hostname."
  value       = aws_db_instance.this.address
}

output "port" {
  description = "RDS instance port."
  value       = aws_db_instance.this.port
}

output "db_name" {
  description = "Database name."
  value       = aws_db_instance.this.db_name
}

output "username" {
  description = "Master DB username."
  value       = aws_db_instance.this.username
  sensitive   = true
}

output "password_secret_arn" {
  description = "ARN of the Secrets Manager secret containing the master password."
  value       = aws_secretsmanager_secret.db_password.arn
}
