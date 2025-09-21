-- Fix final database issues
-- Created: 2025-09-20

USE nexio_db;

-- Add missing index on chat_messages.tenant_id
ALTER TABLE `chat_messages`
ADD INDEX `idx_tenant` (`tenant_id`);

-- Ensure all critical composite indexes exist
ALTER TABLE `chat_messages`
ADD INDEX `idx_tenant_channel` (`tenant_id`, `channel_id`);

-- Add any missing foreign keys for better integrity
-- Note: These are optional but recommended for production

-- Companies to tenants
ALTER TABLE `companies`
ADD CONSTRAINT `fk_companies_tenant`
FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`)
ON DELETE CASCADE ON UPDATE CASCADE;

-- Task lists to tenants
ALTER TABLE `task_lists`
ADD CONSTRAINT `fk_task_lists_tenant`
FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`)
ON DELETE CASCADE ON UPDATE CASCADE;

-- Chat channels to tenants
ALTER TABLE `chat_channels`
ADD CONSTRAINT `fk_chat_channels_tenant`
FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`)
ON DELETE CASCADE ON UPDATE CASCADE;

-- Chat messages to tenants
ALTER TABLE `chat_messages`
ADD CONSTRAINT `fk_chat_messages_tenant`
FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`)
ON DELETE CASCADE ON UPDATE CASCADE;

-- Chat messages to channels
ALTER TABLE `chat_messages`
ADD CONSTRAINT `fk_chat_messages_channel`
FOREIGN KEY (`channel_id`) REFERENCES `chat_channels`(`id`)
ON DELETE CASCADE ON UPDATE CASCADE;

-- Approvals to tenants
ALTER TABLE `approvals`
ADD CONSTRAINT `fk_approvals_tenant`
FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`)
ON DELETE CASCADE ON UPDATE CASCADE;