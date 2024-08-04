/*
 Navicat Premium Data Transfer

 Source Server         : 谢-华为tenancy
 Source Server Type    : MySQL
 Source Server Version : 80032
 Source Host           : 120.46.172.212:3399
 Source Schema         : tenancy

 Target Server Type    : MySQL
 Target Server Version : 80032
 File Encoding         : 65001

 Date: 27/10/2023 15:47:32
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for w_finance_time_key
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_time_key`;
CREATE TABLE `w_finance_time_key`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `thing_key` varchar(0) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '20230621:关账类型：ALL:所有：baoMoney:包车金额相关：pinMoney:拼团价格相关……',
  `describe` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '描述',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '账期key' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_finance_time
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_time`;
CREATE TABLE `w_finance_time`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `company_id` int(0) NULL DEFAULT NULL,
  `belong_time` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '账期：202109',
  `from_time` datetime(0) NULL DEFAULT NULL COMMENT '账期开始时间',
  `to_time` datetime(0) NULL DEFAULT NULL COMMENT '账期结束时间',
  `thing_key` varchar(0) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '20230621:关账类型：ALL:所有：baoMoney:包车金额相关：pinMoney:拼团价格相关……',
  `time_lock` tinyint(1) NULL DEFAULT 0 COMMENT '是否关账（0：未关，1：已关）',
  `lock_user_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '锁账人',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `belong_time`(`belong_time`, `company_id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '账期管理（控制开账和关账）' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_finance_subject
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_subject`;
CREATE TABLE `w_finance_subject`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `company_id` int(0) NULL DEFAULT NULL,
  `pid` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `subject_name` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '科目名称',
  `subject_key` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '科目key：写入代码，一般跟订单类型绑定',
  `cate` tinyint(1) NULL DEFAULT 0 COMMENT '科目分类:1收入科目；2支出科目',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE,
  INDEX `company_id`(`company_id`, `subject_key`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '财务会计科目' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_finance_statics_item
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_statics_item`;
CREATE TABLE `w_finance_statics_item`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `company_id` int(0) NULL DEFAULT NULL,
  `cate` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '分类：bus车辆；dept部门；driver司机；customer客户',
  `finance_item` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '统计项目',
  `item_from` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '数据来源',
  `type` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '1收入；2支出；3计算',
  `sql` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '查询语句Sql',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '财务成本核算项目' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_finance_statement_order
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_statement_order`;
CREATE TABLE `w_finance_statement_order`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `company_id` int(0) NULL DEFAULT NULL,
  `dept_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '部门id',
  `customer_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '客户id',
  `pre_statement_order_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '20220619:关联前序订单',
  `subject_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '20220809:会计科目id',
  `is_ref` tinyint(1) NULL DEFAULT 0 COMMENT '是否退款：0否，1是',
  `Cate` tinyint(1) GENERATED ALWAYS AS (if(`is_ref`,(2 + `change_type`),`change_type`)) VIRTUAL COMMENT '仅展示：收款；付款；收退；付退' NULL,
  `belong_cate` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `user_id` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `manage_account_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `change_type` tinyint(1) NULL DEFAULT 0 COMMENT '1应收，2应付',
  `statement_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '对账单id',
  `statement_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '费用名称',
  `has_statement` tinyint(1) NULL DEFAULT 0 COMMENT '已出账?0否，1是',
  `statement_cate` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '对账单分类',
  `statement_type` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '费用类型',
  `order_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '[广义性]订单id',
  `sub_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '[20220516]子项目id，适用于包车一趟一趟收款的情况',
  `order_type` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '【冗】订单类型：tm_auth；tm_rent；tm_buy；os_buy；公证noary',
  `order_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '订单金额',
  `pay_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '已付金额',
  `original_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '20230813:原价',
  `discount_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '20230813:折让金额',
  `need_pay_prize` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '应付金额',
  `has_confirm` tinyint(1) NULL DEFAULT 0 COMMENT '【冗余】已确认？(0未确认，1已确认)',
  `has_settle` tinyint(1) NULL DEFAULT 0 COMMENT '【冗余】已结算？(0未结算，1已收款/付款)',
  `settle_time` datetime(0) NULL DEFAULT NULL COMMENT '[20220514]结算时间',
  `has_ref` tinyint(1) NULL DEFAULT 0 COMMENT '【退款】有退款？0否，1是',
  `ref_statement_order_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '【退款】退款明细号：本表id',
  `ref_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '【退款】已退款金额',
  `busier_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '业务员',
  `bus_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '【特】摊销车辆',
  `driver_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '【特】摊销司机',
  `belong_table` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '【数据来源表】',
  `belong_table_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '【数据来源表id】',
  `is_needpay` tinyint(1) NULL DEFAULT 0 COMMENT '20230815:标记是否应结算了(用于筛选账单)',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE,
  INDEX `customer_id`(`customer_id`, `order_id`) USING BTREE,
  INDEX `is_ref`(`is_ref`) USING BTREE,
  INDEX `has_statement`(`has_statement`) USING BTREE,
  INDEX `order_id`(`order_id`) USING BTREE,
  INDEX `has_settle`(`has_settle`) USING BTREE,
  INDEX `has_used`(`has_used`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '对账单-订单' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_finance_statement
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_statement`;
CREATE TABLE `w_finance_statement`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `company_id` int(0) NULL DEFAULT NULL,
  `dept_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `customer_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '客户id',
  `pre_statement_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '20220619:前序账单id(关联前序订单)',
  `subject_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '20220809:会计科目id',
  `belong_cate` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '对账单归属：customer:公司；user:个人',
  `Cate` tinyint(0) GENERATED ALWAYS AS (if(`is_ref`,(2 + `change_type`),`change_type`)) VIRTUAL COMMENT '1收款；2付款；付退；4收退' NULL,
  `user_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '客户对账人',
  `manage_account_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '冲账账户',
  `order_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '单订单使用',
  `is_ref` tinyint(1) NULL DEFAULT NULL,
  `ref_statement_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `change_type` tinyint(1) NULL DEFAULT NULL COMMENT '1应收，2应付',
  `statement_type` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '款项类型',
  `statement_cate` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '对账单类型',
  `statement_name` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '对账单名称',
  `goods_name` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '相关商品名称，逗号分隔',
  `need_pay_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '应付金额',
  `ref_prize` decimal(10, 2) NULL DEFAULT NULL COMMENT '退款金额',
  `remainPrize` decimal(10, 2) GENERATED ALWAYS AS ((`need_pay_prize` - ifnull(`ref_prize`,0))) VIRTUAL COMMENT '剩余金额' NULL,
  `start_time` datetime(0) NULL DEFAULT NULL COMMENT '开始时间',
  `end_time` datetime(0) NULL DEFAULT NULL COMMENT '结束时间',
  `file_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '文件id',
  `has_confirm` tinyint(1) NULL DEFAULT 0 COMMENT '客户已确认（0：未确认，1：已确认）',
  `has_settle` tinyint(1) NULL DEFAULT 0 COMMENT '已结算？(0未结算，1已收款/付款)',
  `pay_by` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '20230904:调用第三方支付前，标记付款渠道',
  `has_front_pay` tinyint(1) NULL DEFAULT 0 COMMENT '[20220602]：前端上报的已付款状态',
  `settle_time` datetime(0) NULL DEFAULT NULL COMMENT '[20220514]结算时间',
  `account_log_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '结算关联资金流水id，有id才能负值结算',
  `account_bill_time` datetime(0) NULL DEFAULT NULL COMMENT '[冗]账单结算时间，根据account_id取',
  `busier_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `customer_id`(`customer_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '对账单' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_finance_staff_fee_type
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_staff_fee_type`;
CREATE TABLE `w_finance_staff_fee_type`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `company_id` int(0) NULL DEFAULT NULL,
  `fee_group` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '费用归属：office办公室；driver司机；',
  `fee_key` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '费用key',
  `fee_name` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '报销单号',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '员工报销类型' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_finance_staff_fee_list
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_staff_fee_list`;
CREATE TABLE `w_finance_staff_fee_list`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `company_id` int(0) NULL DEFAULT NULL,
  `dept_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '部门id',
  `fee_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '费用id',
  `fee_type` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '费用类型',
  `bus_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '归属车辆',
  `money` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工申请报销金额',
  `real_money` decimal(10, 2) NULL DEFAULT NULL COMMENT '20231008：领导同意报销金额',
  `pay_money` decimal(10, 2) NULL DEFAULT NULL COMMENT '20231008：财务已付金额',
  `annex` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '附件',
  `order_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '20231008:订单编号',
  `bao_bus_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '20231008:趟次编号',
  `has_settle` int(0) NULL DEFAULT 0 COMMENT '状态：0未结；1已结',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '员工报销明细' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_finance_staff_fee
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_staff_fee`;
CREATE TABLE `w_finance_staff_fee`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `company_id` int(0) NULL DEFAULT NULL,
  `dept_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '部门id',
  `order_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '20220623 订单编号：关联钱款处理',
  `fee_group` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '费用归属：office办公室；driver司机；',
  `fee_sn` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '报销单号',
  `user_id` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '报销人',
  `apply_time` datetime(0) NULL DEFAULT NULL COMMENT '申请时间',
  `money` decimal(10, 2) NULL DEFAULT NULL COMMENT '报销金额',
  `annex` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '附件',
  `bus_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '20221007:车辆id',
  `acc_status` int(0) NULL DEFAULT 0 COMMENT '会计状态：0待审批；1已同意，2已拒绝',
  `fin_status` int(0) NULL DEFAULT 0 COMMENT '出纳状态：0待审批；1已同意，2已拒绝',
  `boss_status` int(0) NULL DEFAULT 0 COMMENT '业务状态：0待审批；1已同意，2已拒绝',
  `has_settle` int(0) NULL DEFAULT 0 COMMENT '状态：0未结；1已结',
  `source_id` varchar(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '20221007:跨系统迁移数据',
  `need_appr` tinyint(1) NULL DEFAULT NULL COMMENT '20230724:需要审核？通过一定逻辑计算',
  `approval_thing_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '20230704:审批事项编号',
  `audit_status` tinyint(0) NULL DEFAULT NULL COMMENT '事项审批状态：0待审批(尚无人批);1已通过；2不通过；3审批中(有人批，但还没批完)；4已撤销',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `source_id`(`source_id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '员工报销' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_finance_receipt
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_receipt`;
CREATE TABLE `w_finance_receipt`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `company_id` int(0) NULL DEFAULT NULL,
  `receipt_sn` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '发票编号',
  `receipt_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '发票类型：1普票，2专票',
  `receipt_nn` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '发票代码',
  `receipt_time` datetime(0) NULL DEFAULT NULL COMMENT '开票时间',
  `receipt_money` decimal(10, 2) NULL DEFAULT NULL COMMENT '开票金额',
  `receipt_rate` decimal(10, 2) NULL DEFAULT NULL COMMENT '发票税率',
  `customer_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '客户名称',
  `customer_code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '纳税人识别号',
  `pic` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '附图1',
  `send_status` int(0) NULL DEFAULT NULL COMMENT '送票状态：0待送票，1送票中，2已送达',
  `receive_name` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '收票人',
  `receive_time` datetime(0) NULL DEFAULT NULL COMMENT '收票时间',
  `money_status` int(0) NULL DEFAULT NULL COMMENT '收款状态：0未结算，1结算中，2已结清',
  `money_time` datetime(0) NULL DEFAULT NULL COMMENT '收款时间',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '开票明细表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_finance_manage_account_log
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_manage_account_log`;
CREATE TABLE `w_finance_manage_account_log`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `company_id` int(0) NULL DEFAULT NULL,
  `balance_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '20230726:冲账流水号（多笔同号标识同时销账）',
  `manage_account_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '管理账户id，finance_manage_account表',
  `account_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '[冗]账户id，finance_account表',
  `account_type` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '[冗]冗余account_id的类型',
  `belong_table` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '账户归属表',
  `belong_table_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '账户归属id，冗余',
  `change_type` int(0) NULL DEFAULT 1 COMMENT '1进账，2出账',
  `money` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '变动金额',
  `current_money` decimal(10, 2) NULL DEFAULT NULL COMMENT '当前余额',
  `before_money` decimal(10, 2) NULL DEFAULT NULL COMMENT '变动前',
  `reason` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '异动原因',
  `bill_type` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '单据类型',
  `bill_sn` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '单据号',
  `bill_time` datetime(0) NULL DEFAULT NULL COMMENT '资金变动时间',
  `from_table` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '来源表',
  `from_table_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '来源表id',
  `file_id` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '附件',
  `busier_id` char(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `account_id`(`manage_account_id`) USING BTREE,
  INDEX `from_table_id`(`from_table_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE,
  INDEX `money`(`money`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '资金账户异动表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_finance_manage_account
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_manage_account`;
CREATE TABLE `w_finance_manage_account`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `company_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `belong_table` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '账户归属表',
  `belong_table_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '账户归属id，如用户id，customer_id等',
  `account_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '账户名称',
  `account_type` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '账户类型(cash：现金,wechat：微信，corporate：对公账，meituan：美团账户)',
  `account_no` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '账号',
  `money` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '账户余额',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `belong_table`(`belong_table`, `belong_table_id`, `account_type`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '财务账户表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_finance_income_pay
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_income_pay`;
CREATE TABLE `w_finance_income_pay`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `company_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '',
  `income_pay_sn` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '支付单号',
  `pay_by` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '支付渠道：wechat:微信；money:余额',
  `income_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '收款单id，tr_finance表',
  `order_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '订单id，不一定关联得了',
  `money` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '收款单-金额',
  `income_sn` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '收款单-号',
  `income_status` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '收款状态//todo待收款、finish已收款、close订单关闭',
  `describe` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '支付描述',
  `customer_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '支付客户id',
  `user_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '支付用户id',
  `user_account` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '用户账户名',
  `user_account_no` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '用户账号',
  `account_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '收款账户id，账户表',
  `into_account` int(0) NULL DEFAULT 0 COMMENT '入账状态：0未入账，1已入账',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `income_id`(`income_id`) USING BTREE,
  INDEX `user_id`(`user_id`) USING BTREE,
  INDEX `customer_id`(`customer_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '收款记录(用户支付)' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_finance_account_log
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_account_log`;
CREATE TABLE `w_finance_account_log`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `company_id` int(0) NULL DEFAULT NULL,
  `dept_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '部门id',
  `pre_log_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `account_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '账户id，finance_account表',
  `customer_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '付款客户id',
  `user_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '付款人',
  `e_account_no` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '账号',
  `change_type` int(0) NULL DEFAULT 1 COMMENT '1进账，2出账',
  `money` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '变动',
  `current_money` decimal(10, 2) NULL DEFAULT NULL COMMENT '当前余额',
  `before_money` decimal(10, 2) NULL DEFAULT NULL COMMENT '变动前',
  `reason` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '异动原因',
  `bill_type` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '单据类型',
  `bill_sn` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '单据号',
  `bill_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '资金变动时间',
  `yearmonthDate` datetime(0) GENERATED ALWAYS AS (date_format(`bill_time`,_utf8mb4'%Y-%m-%d')) VIRTUAL NULL,
  `from_table` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '来源表',
  `from_table_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '来源表id',
  `statement_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '关联对账单',
  `file_id` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '附件',
  `busier_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '业务员',
  `is_check` tinyint(1) NULL DEFAULT 0 COMMENT '核对？0否；1是',
  `is_user_noticed` tinyint(1) NULL DEFAULT 0 COMMENT '20230102用户已通知',
  `is_adm_noticed` tinyint(1) NULL DEFAULT 0 COMMENT '20230102管理已通知',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `account_id`(`account_id`) USING BTREE,
  INDEX `from_table_id`(`from_table_id`) USING BTREE,
  INDEX `user_id`(`user_id`) USING BTREE,
  INDEX `customer_id`(`customer_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE,
  INDEX `money`(`money`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '资金账户异动表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for w_finance_account
-- ----------------------------
DROP TABLE IF EXISTS `w_finance_account`;
CREATE TABLE `w_finance_account`  (
  `id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `company_id` int(0) NULL DEFAULT NULL,
  `customer_id` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '客户id，预付款账用',
  `account_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '账户名称',
  `account_cate` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL COMMENT '账户分类：fund:资金账；manage:管理账',
  `account_type` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '账户类型(cash：现金,wechat：微信，corporate：对公账，meituan：美团账户)',
  `is_handle` tinyint(1) NULL DEFAULT NULL COMMENT '是否可手工录入(0否,1是)',
  `is_real` tinyint(1) NULL DEFAULT 1 COMMENT '[20220525]是否真实账户(真实账户才统计收入)',
  `account_no` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '账号',
  `money` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '账户余额',
  `accept_msg` tinyint(1) NULL DEFAULT 1 COMMENT '接收推送',
  `sort` int(0) NULL DEFAULT 1000 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态(0禁用,1启用)',
  `has_used` tinyint(1) NULL DEFAULT 0 COMMENT '有使用(0否,1是)',
  `is_lock` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未锁，1：已锁）',
  `is_delete` tinyint(1) NULL DEFAULT 0 COMMENT '锁定（0：未删，1：已删）',
  `remark` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL COMMENT '备注',
  `creater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '创建者，user表',
  `updater` char(19) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT '' COMMENT '更新者，user表',
  `create_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `update_time` datetime(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `company_id`(`company_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `update_time`(`update_time`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci COMMENT = '财务账户表' ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
