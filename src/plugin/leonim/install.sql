-- Leonim SQL: Webman IM 扩展

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 好友申请表: wa_friend_requests
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wa_friend_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
    `from_user_id` int(11) NOT NULL COMMENT '申请人 ID',
    `to_user_id` int(11) NOT NULL COMMENT '接收申请用户 ID',
    `message` varchar(255) DEFAULT NULL COMMENT '申请留言',
    `status` int(11) NOT NULL DEFAULT '0' COMMENT '状态（0=未处理, 1=同意, 2=拒绝）',
    `is_read` int(11) NOT NULL DEFAULT '0' COMMENT '是否已读（0=未读, 1=已读）',
    `group_name` varchar(50) DEFAULT NULL COMMENT '分组（可为空）',
    `remark` varchar(50) DEFAULT NULL COMMENT '备注名（可为空）',
    `tags` varchar(255) DEFAULT NULL COMMENT '好友标签（可选，JSON 或逗号分隔）',
    `created_at` datetime DEFAULT NULL COMMENT '申请时间',
    `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='好友申请表';

-- --------------------------------------------------------
-- 给 wa_users 表增加 uuid 字段
-- --------------------------------------------------------
ALTER TABLE `wa_users`
    ADD COLUMN IF NOT EXISTS `uuid` CHAR(36) NOT NULL COMMENT '用户唯一标识 UUID' AFTER `id`;

-- 给 uuid 添加唯一索引
ALTER TABLE `wa_users`
    ADD UNIQUE INDEX IF NOT EXISTS `idx_uuid` (`uuid`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
