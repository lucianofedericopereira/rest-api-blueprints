output "service_name" {
  description = "Name of the ECS service."
  value       = aws_ecs_service.app.name
}

output "service_id" {
  description = "ID of the ECS service."
  value       = aws_ecs_service.app.id
}

output "task_definition_arn" {
  description = "ARN of the latest task definition revision."
  value       = aws_ecs_task_definition.app.arn
}

output "task_execution_role_arn" {
  description = "ARN of the ECS task execution IAM role."
  value       = aws_iam_role.task_execution.arn
}

output "task_role_arn" {
  description = "ARN of the ECS task IAM role (application process identity)."
  value       = aws_iam_role.task.arn
}

output "log_group_name" {
  description = "CloudWatch log group name for this service."
  value       = aws_cloudwatch_log_group.app.name
}

output "log_group_arn" {
  description = "CloudWatch log group ARN."
  value       = aws_cloudwatch_log_group.app.arn
}

output "target_group_arn" {
  description = "ARN of the ALB target group."
  value       = aws_lb_target_group.app.arn
}
