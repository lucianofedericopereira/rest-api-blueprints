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

variable "image_tag_mutability" {
  description = "Image tag mutability setting (IMMUTABLE recommended for production)."
  type        = string
  default     = "IMMUTABLE"
}

variable "max_image_count" {
  description = "Maximum number of images to retain in the repository."
  type        = number
  default     = 10
}
