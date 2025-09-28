-- Leonim SQL: Webman IM 扩展

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 给 wa_users 表增加 uuid 字段
-- --------------------------------------------------------

-- ================================
-- 1. 如果不存在 uuid 字段，则添加
-- ================================
ALTER TABLE `wa_users`
    ADD COLUMN `uuid` CHAR(36) NOT NULL COMMENT '用户唯一标识 UUID' AFTER `id`;

-- ==========================================
-- 2. 创建触发器：插入时自动设置 UUID
-- ==========================================
DELIMITER $$

CREATE TRIGGER `wa_users_before_insert_set_uuid`
    BEFORE INSERT ON `wa_users`
    FOR EACH ROW
BEGIN
    IF NEW.`uuid` IS NULL OR NEW.`uuid` = '' THEN
        SET NEW.`uuid` = UUID();
END IF;
END$$

DELIMITER ;

-- ======================================
-- 3. 补齐历史数据中 uuid 为空的记录
-- ======================================
UPDATE `wa_users`
SET `uuid` = UUID()
WHERE `uuid` IS NULL OR `uuid` = '';

-- ================================
-- 4. 添加唯一索引
-- ================================
ALTER TABLE `wa_users`
    ADD UNIQUE INDEX `idx_uuid` (`uuid`);

-- --------------------------------------------------------
-- 好友申请表: wa_friend_requests
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wa_friend_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
    `from_user_id` char(36) NOT NULL COMMENT '申请人 ID',
    `to_user_id` char(36) NOT NULL COMMENT '接收申请用户 ID',
    `message` varchar(255) DEFAULT NULL COMMENT '申请留言',
    `status` int(11) NOT NULL DEFAULT '0' COMMENT '状态（0=未处理, 1=同意, 2=拒绝）',
    `is_read` int(11) NOT NULL DEFAULT '0' COMMENT '是否已读（0=未读, 1=已读）',
    `group_name` varchar(50) DEFAULT NULL COMMENT '好友分组（可为空）',
    `remark` varchar(50) DEFAULT NULL COMMENT '备注名（可为空）',
    `tags` varchar(255) DEFAULT NULL COMMENT '好友标签（可选，JSON 或逗号分隔）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申请时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='好友申请表';
-- ============================================
-- 好友表 wa_friends
-- ============================================
CREATE TABLE IF NOT EXISTS `wa_friends` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `user_id` int(11) NOT NULL COMMENT '用户ID',
    `friend_id` int(11) NOT NULL COMMENT '好友ID',
    `remark` varchar(50) DEFAULT NULL COMMENT '好友备注名',
    `group_name` varchar(50) DEFAULT NULL COMMENT '好友分组',
    `tags` varchar(255) DEFAULT NULL COMMENT '好友标签（JSON 或逗号分隔）',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态（1=正常, 0=已删除/拉黑）',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_user_friend` (`user_id`,`friend_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='好友关系表';
-- ============================================
-- 用户黑名单表 wa_user_blacklist
-- ============================================
CREATE TABLE `wa_user_blacklist` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL COMMENT '操作者用户ID',
    `blocked_user_id` INT UNSIGNED NOT NULL COMMENT '被拉黑用户ID',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_user_blocked` (`user_id`, `blocked_user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户黑名单表';
-- ============================================
-- 会话表 wa_conversations
-- ============================================
CREATE TABLE `wa_conversations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '会话ID',
    `type` TINYINT NOT NULL DEFAULT 1 COMMENT '会话类型：1=单聊, 2=群聊',
    `name` VARCHAR(255) DEFAULT NULL COMMENT '会话名称，群聊有效',
    `avatar` VARCHAR(255) DEFAULT NULL COMMENT '会话头像，群聊有效',
    `target_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '单聊时的目标用户ID',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `is_active` TINYINT DEFAULT 1 COMMENT '是否激活/存在，0=解散或删除',
    PRIMARY KEY (`id`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会话表';
-- ============================================
-- 消息表 wa_messages
-- ============================================
CREATE TABLE `wa_messages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '消息ID',
    `conversation_id` BIGINT UNSIGNED NOT NULL COMMENT '会话ID',
    `from_user_id` BIGINT UNSIGNED NOT NULL COMMENT '发送者用户ID',
    `type` TINYINT NOT NULL DEFAULT 1 COMMENT '消息类型：1=文本,2=图片,3=视频,4=语音,5=语音电话,6=视频电话',
    `content` TEXT COMMENT '消息内容或文件路径',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '发送状态：1=正常,2=撤回,3=失败',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '发送时间',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_conversation_id` (`conversation_id`),
    KEY `idx_from_user_id` (`from_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='消息表';
-- ============================================
-- 群聊成员表 wa_conversation_members
-- ============================================
CREATE TABLE `wa_conversation_members` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `conversation_id` BIGINT UNSIGNED NOT NULL COMMENT '群聊ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '成员用户ID',
    `role` TINYINT DEFAULT 1 COMMENT '角色：1=普通成员,2=管理员,3=群主',
    `is_muted` TINYINT DEFAULT 0 COMMENT '是否禁言 0=否 1=是',
    `last_read_message_id` BIGINT UNSIGNED DEFAULT 0 COMMENT '已读消息ID，用于未读统计',
    `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '加入时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_conversation_user` (`conversation_id`, `user_id`),
    KEY `idx_conversation_id` (`conversation_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='群聊成员表';
-- ============================================
-- 群聊未读统计表 wa_conversation_member_unread
-- ============================================
CREATE TABLE `wa_conversation_member_unread` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `conversation_id` BIGINT UNSIGNED NOT NULL COMMENT '会话ID',
    `message_id` BIGINT UNSIGNED NOT NULL COMMENT '消息ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `is_read` TINYINT DEFAULT 0 COMMENT '是否已读 0=未读 1=已读',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '记录创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_conversation_message_user` (`conversation_id`,`message_id`,`user_id`),
    KEY `idx_conversation_id` (`conversation_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='群聊消息未读状态表';
-- ============================================
-- 记录用户删除消息 wa_message_user_deleted
-- ============================================
CREATE TABLE `wa_message_user_deleted` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT NOT NULL COMMENT '执行删除的用户ID',
    `message_id` BIGINT NOT NULL COMMENT '消息ID',
    `deleted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '删除时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_message_unique` (`user_id`,`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='记录用户删除消息';

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
