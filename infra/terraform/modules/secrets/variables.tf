variable "project" {
  description = "Project name."
  type        = string
}

variable "environment" {
  description = "Deployment environment."
  type        = string
}

variable "stack_name" {
  description = "Stack identifier (e.g. fastapi, nestjs, gin)."
  type        = string
}

variable "secret_values" {
  description = "Map of secret key/value pairs to store. ENCRYPTION_KEY must be exactly 32 bytes."
  type        = map(string)
  sensitive   = true
}

variable "recovery_window_days" {
  description = "Number of days before a deleted secret is permanently removed."
  type        = number
  default     = 7
}
