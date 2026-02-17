variable "project" {
  description = "Project name used in resource naming."
  type        = string
}

variable "environment" {
  description = "Deployment environment."
  type        = string
}

variable "identifier" {
  description = "Unique RDS instance identifier suffix (e.g. 'main')."
  type        = string
  default     = "main"
}

variable "vpc_id" {
  description = "VPC ID."
  type        = string
}

variable "subnet_ids" {
  description = "Private subnet IDs for the DB subnet group."
  type        = list(string)
}

variable "security_group_id" {
  description = "Security group ID that allows DB traffic from ECS."
  type        = string
}

variable "db_name" {
  description = "Initial database name."
  type        = string
  default     = "iso27001"
}

variable "db_username" {
  description = "Master DB username."
  type        = string
  default     = "dbadmin"
}

variable "instance_class" {
  description = "RDS instance class."
  type        = string
  default     = "db.t3.micro"
}

variable "allocated_storage" {
  description = "Initial storage allocation in GiB."
  type        = number
  default     = 20
}

variable "engine_version" {
  description = "PostgreSQL engine version."
  type        = string
  default     = "16"
}

variable "backup_retention_days" {
  description = "Automated backup retention period (days). A.17."
  type        = number
  default     = 7
}
