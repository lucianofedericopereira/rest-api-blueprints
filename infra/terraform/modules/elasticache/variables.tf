variable "project" {
  description = "Project name used in resource naming."
  type        = string
}

variable "environment" {
  description = "Deployment environment."
  type        = string
}

variable "vpc_id" {
  description = "VPC ID."
  type        = string
}

variable "subnet_ids" {
  description = "Private subnet IDs for the cache subnet group."
  type        = list(string)
}

variable "security_group_id" {
  description = "Security group ID that allows Redis traffic from ECS."
  type        = string
}

variable "node_type" {
  description = "ElastiCache node type."
  type        = string
  default     = "cache.t3.micro"
}

variable "engine_version" {
  description = "Redis engine version."
  type        = string
  default     = "7.1"
}

variable "num_cache_clusters" {
  description = "Number of cache clusters in the replication group (min 2 for failover)."
  type        = number
  default     = 1
}
