# PackSubmit 包装方案提交评审系统（Go 版）

> 面向汽车零部件供应链的「包装确认单」在线填报、评审、归档与变更管理系统。
> 本仓库已由 ThinkPHP 重构为 **Go（Gin + GORM）+ Vue 3 + Element Plus**，PHP 代码已移除。

---

## 一、项目定位与解决的痛点

供应商每供一个零件，都要向客户提交一份**包装确认单**（包装方式、包装箱、托盘、图片、操作说明等）。传统方式靠邮件和 Excel 往返，容易出现版本混乱、零件号超出范围、资料不全、归档后无法追溯变更等问题。

| 传统痛点 | 本系统的做法 |
| --- | --- |
| 版本混乱、说不清哪版有效 | 状态机 + 版本号（V1/V2…）+ 全程审计日志 + 变更两版并存 |
| 供应商乱填零件号 | **受控建单**：管理员维护「供应商 + 零件号」清单，草稿由系统生成，供应商不能自建 |
| 资料填写不全 | 提交前统一完整性校验（20 项文本、数值、尺寸、托盘、四类图片） |
| 归档后改了什么查不到 | 变更生成独立新记录，新版本归档生效后旧版本自动置为「已作废」 |
| 同一供应商多个账号数据割裂 | 方案归属 `supplier_id`，同供应商账号互通（方案、附件、联系人） |
| 图片重复占空间、可被直链盗取 | 内容 MD5 去重 + 引用计数删除 + 文件放 Web 根目录外，仅鉴权接口可读 |
| 主数据改名污染历史单据 | 方案保存快照，客户/联系人改名不回写历史方案 |
| 没有审计 | 全局审计日志 + 方案流程日志 + 主数据维护日志 |

---

## 二、技术栈

| 层 | 技术 |
| --- | --- |
| 后端 | Go 1.27、Gin v1.12、GORM v1.31 |
| 鉴权 | JWT（HS256）+ token 版本号（改密即失效）+ 内存黑名单 |
| Excel | Excelize v2（按模板填充 + 图片锚点嵌入） |
| 数据库 | MySQL 8.0 / 5.7+，utf8mb4，**无表前缀、ULID 主键、GORM 软删** |
| 前端 | Vue 3、Vite 6、Element Plus、Pinia、Vue Router、Axios |
| 前端权限 | 后端下发 `menus / routes / permissions`，动态 `addRoute` + `v-permission` |

---

## 三、目录结构

```
backend/
├── cmd/server/main.go             # 入口：配置 → 建库 → 迁移 → 服务 → 优雅退出
├── configs/config.yaml            # 配置（可用 PS_ 环境变量覆盖）
├── internal/
│   ├── config/                    # YAML + 环境变量
│   ├── database/                  # 连接、迁移执行、演示数据
│   ├── model/                     # GORM 模型（11 张表）
│   ├── dto/                       # 请求结构
│   ├── service/                   # 业务：方案状态机 / 附件 / 导出 / 审计 / 认证 / 主数据
│   ├── handler/                   # 参数绑定与响应
│   ├── middleware/                # Recovery / 安全头 / CORS / JWT / 权限
│   ├── router/router.go           # 路由装配
│   ├── pkg/{auth,resp,ulidx}      # JWT+菜单+密码策略、统一响应、ULID
│   └── version/
├── migrations/0001_init.sql       # 显式迁移脚本
├── templates/                     # 包装确认单 Excel 模板
└── storage/attachments/           # 附件（Web 根目录之外）

frontend/
├── src/
│   ├── api/                       # 按模块拆分（request 统一拦截 + 401 静默续期）
│   ├── directives/permission.js   # v-permission
│   ├── layout/index.vue           # 侧边栏（后端菜单树）+ 修改密码
│   ├── router/{index,permission}.js
│   ├── store/user.js              # token / user / menus / routes / permissions
│   └── views/{login,dashboard,proposal,contacts,audit,error}
└── vite.config.js                 # /api 代理到 127.0.0.1:8090
```

---

## 四、快速开始

### 环境要求

- Go 1.27+
- MySQL 5.7+ / 8.0
- Node.js 18+（仅前端构建需要）

### 1. 后端

```bash
cd backend
go mod tidy

# 建库 + 执行迁移 + 初始化演示数据（空库才生效）
go run ./cmd/server -config configs/config.yaml -migrate -seed

# 启动（默认 :8090）
go run ./cmd/server -config configs/config.yaml
```

默认数据库 `pack_submit`（见 `configs/config.yaml`；数据库账号密码建议通过 `PS_DB_USER` / `PS_DB_PASSWORD` 注入）。
生产环境请通过 `PS_DB_PASSWORD`、`PS_JWT_SECRET` 覆盖，勿提交真实密钥。

### 2. 前端

```bash
cd frontend
npm install
npm run dev        # http://localhost:5273，/api 代理到 :8090
npm run build      # 产物在 frontend/dist
```

### 3. 单二进制部署（可选）

```bash
cd frontend && npm run build
cd ../backend
CGO_ENABLED=0 go build -o bin/packsubmit ./cmd/server
PS_SERVER_STATIC_DIR=../frontend/dist PS_SERVER_MODE=release ./bin/packsubmit -config configs/config.yaml
```

后端会自动托管 `static_dir` 下的前端产物（非 `/api` 的 GET 回退到 `index.html`）。

### 4. 同域部署（前端与后端绑定同一域名）

前端与接口必须同源：页面走 `/`，接口走 `/api/v1`。两种做法任选其一：

- **后端托管（最简）**：`npm run build` 后设置 `PS_SERVER_STATIC_DIR=../frontend/dist`，由 Go 进程同时提供页面与接口，只需暴露一个端口（默认 `8090`）。
- **Nginx / Apache 反代**：静态资源交给 Web 服务器，`/api/` 转发到后端：

```nginx
server {
    listen 80;
    server_name packsubmit.example.com;

    root /srv/packsubmit/frontend/dist;
    index index.html;

    location /api/ {
        proxy_pass http://127.0.0.1:8090;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

> 开发环境把域名指到 Vite（`5273`）时，反向代理需透传 `Upgrade` / `Connection` 头，否则 HMR WebSocket 无法连接；接口仍由 Vite 代理转发到后端 `8090`。
> 跨域部署（前后端不同域名）时才需要配置 `cors.allow_origins`，且不支持 `*`。

### 演示账号

| 账号 | 密码 | 角色 |
| --- | --- | --- |
| `admin` | `admin123` | 管理员 |
| `reviewer` | `reviewer123` | 评审员 |
| `submitter` | `submitter123` | 供应商（供应商A） |
| `viewer` | `viewer1234` | 查看者（只读） |

> 密码策略：8–32 位且同时包含字母和数字；首次登录后请立即修改。

---

## 五、请求链路与响应约定

```
Vue → axios（Bearer Token）
  → CORS / SecurityHeaders / JWT（回查用户状态 + token_version）
  → RequirePermission（接口级权限码）
  → Handler → Service（事务）→ GORM → MySQL
  → { code, message, data }
```

- `code = 0` 成功；`1001` 参数错误、`2001` 未登录、`2002` 无权限、`3001` 业务错误、`3002` 限流、`5001` 服务异常。
- HTTP 状态码对齐：401 / 403 / 429；其余业务错误返回 200 + 业务码（前端统一弹 `message`）。
- 分页统一 `{ list, total, page, page_size }`。
- 401 时前端自动调用 `/auth/refresh` 静默续期并重放原请求，失败才跳登录页。

---

## 六、角色与权限

| 角色 | role | 数据范围 | 能力 |
| --- | --- | --- | --- |
| 供应商 | 1 | 本供应商全部方案 | 编辑/提交/撤回/删除变更草稿/发起变更/导出；维护本供应商联系人 |
| 评审员 | 2 | 所有非草稿方案 | 查看、评审、导出 |
| 管理员 | 3 | 全部数据 | 全量查看、评审、批量归档、代维护联系人、审计日志 |
| 查看者 | 4 | 全部方案（含草稿） | 只读查看 + 导出 |

权限码（后端 `RequirePermission` 与前端 `v-permission` 共用）：

```
proposal:view  proposal:update  proposal:submit  proposal:withdraw  proposal:delete
proposal:review  proposal:archive  proposal:change  proposal:export  proposal:all
contact:manage  master:manage  user:manage  audit:view  report:view
```

前端路由守卫与按钮权限仅用于体验，后端每个接口独立鉴权。

---

## 七、方案状态机与版本

```
0 草稿 → 1 待评审 → 2 评审通过 → 4 已归档 → 5 已归档-已变更-作废
           ↓ 驳回(3)                     ↑
       3 驳回 → 1 待评审（版本 +1）      └─ 变更方案归档生效
           ↑
      1 待评审 → 0 草稿（撤回，清空提交时间）
```

- 初始版本 V1；**驳回后重新提交 +1**；**发起变更 = 源版本 +1**；其他操作不改版本。
- 只有草稿/驳回可编辑、可提交；只有待评审可撤回（已产生评审时间不允许）；只有评审通过可归档。
- 只有已归档可发起变更，且同一源方案同时只允许一条进行中的变更。
- 供应商只能删除变更草稿；导入生成的草稿不可删除（避免绕过受控建单）。
- 方案编号 `PS + YYYYMMDD + 4 位流水`，生成时包含软删记录，避免重号。

---

## 八、数据模型（新 schema）

设计约定：**ULID 主键（char(26)）、无表前缀、无物理外键、统一 `created_at/updated_at/deleted_at`**，删改由应用层事务与唯一索引保证。

| 表 | 用途 | 关键约束 |
| --- | --- | --- |
| `users` | 账号 | `username` 唯一；`supplier_id` 关联供应商；`token_version` |
| `suppliers` | 供应商档案（名称/代码/地址） | `name` 唯一 |
| `supplier_contacts` | 供应商联系人（多联系人 + 默认） | `supplier_id` 索引 |
| `customers` | 客户档案（自动带出资料） | `name` 唯一 |
| `supplier_parts` | 指定零件号清单（受控建单来源） | `(supplier_id, part_no)` 唯一 |
| `proposals` | 包装方案主表 | `proposal_no` 唯一；`status`、`supplier_id`、`source_proposal_id` 索引 |
| `attachments` | 附件 | `proposal_id` 为空表示暂存；`md5`、`file_path` 索引 |
| `dicts` | 字典 | `(dict_type, dict_value)` 唯一 |
| `audit_logs` | 全局审计 + 方案流程日志 | `proposal_id`、`action`、`created_at` 索引 |
| `master_logs` | 客户/供应商/用户维护日志 | `(biz_type, target_id)` |
| `part_logs` | 零件号维护日志 | `(supplier_id, part_no)` |

方案字段分组（与包装确认单一致）：标识与归属、流程与评审、客户资料快照、供应商资料快照、包装信息、托盘信息。

---

## 九、API 清单（前缀 `/api/v1`）

**公共**

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| POST | `/auth/login` | 登录（账号 + IP 双维度限流，超出 429） |
| POST | `/auth/refresh` | 刷新 token（旧 token 立即拉黑） |
| GET | `/health` | 健康检查 + 版本号 |

**登录后**

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| POST | `/auth/logout` | 退出（token 拉黑） |
| GET | `/auth/me` | 当前用户 + 菜单/路由/权限 |
| PUT | `/auth/password` | 改密（递增 token_version，返回新 token） |
| GET | `/dicts/options` | 字典选项 |
| GET | `/master/customers` `/suppliers` `/my-supplier` `/parts` | 主数据读取 |
| GET/POST/PUT/DELETE | `/supplier-contacts` | 联系人维护（供应商限本家，管理员可代维护） |
| GET | `/proposals` | 方案列表（按角色限定数据范围） |
| GET | `/proposals/statistics` | 工作台统计 |
| GET | `/proposals/projects` | 项目车型下拉 |
| GET | `/proposals/:id` | 详情（附件 + 流程日志 + 版本记录 + 操作权限 + 变更关系） |
| POST | `/proposals` | 新增（仅管理员/受控入口） |
| PUT | `/proposals/:id` | 编辑（草稿/驳回） |
| DELETE | `/proposals/:id` | 删除草稿 |
| POST | `/proposals/:id/submit` | 提交评审（完整性校验） |
| POST | `/proposals/:id/withdraw` | 撤回 |
| POST | `/proposals/:id/review` | 评审（通过/驳回） |
| POST | `/proposals/:id/change` | 发起变更 |
| GET | `/proposals/:id/export/excel` | 导出包装确认单 |
| POST | `/attachments/upload` | 上传（暂存或直接挂方案，MD5 去重） |
| POST | `/attachments/bind` | 绑定暂存附件 |
| GET | `/attachments/:id/download` `/preview` | 下载 / 预览（鉴权） |
| DELETE | `/attachments/:id` | 删除（引用计数清理物理文件） |

**管理员（`/admin`，role=3）**

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| POST | `/admin/proposals/archive` | 批量归档（变更归档时自动作废原方案） |
| GET | `/admin/audit-logs` | 全局审计日志 |

---

## 十、关键实现细节

**1. 派生字段一律后端计算**

`spec = 包装箱外长*宽*高mm`、`quantity = 存放数量`、`pallet_parts_qty = 每层 × 层数 × 存放数量`、`write_date = 当天`。
前端传值被忽略；尺寸只允许整数，带小数直接拒绝；不带托盘时托盘字段清零。

**2. 提交前完整性校验**（`ProposalService.assertSubmitReady`）

20 项文本必填、3 项数值 > 0、两组尺寸齐全（带托盘再校验托盘 6 项）、3 个是否项必须选择、四类图片齐全、毛重 > 单件净重 × 存放件数。保存草稿不做提交级校验。

**3. 以档案为准，防前端伪造**

供应商名称/代码/地址、零件名称/项目车型、客户资料均由后端按 `supplier_id` / `part_no` / `customer_id` 从档案读取并写入快照，前端提交的快照字段不生效（联系人资料除外，允许现场调整）。

**4. 附件**

- 白名单 `pdf/dwg/xlsx/docx/png/jpg`，单文件 ≤ 10MB。
- 图片用 `image.DecodeConfig` 按内容识别真实格式，改后缀伪装被拒。
- 每个图片位只允许 1 张。
- 内容 MD5 去重：只复用「物理文件仍存在」的记录；删除按 `file_path` 引用计数，最后一条引用删除时才删文件。
- 物理目录 `backend/storage/attachments/YYYYMM/`（Web 根目录外），路径解析强制校验前缀、拒绝 `..`。

**5. Excel 导出**

以 `backend/templates/包装确认单-模板 20260730.xlsx` 为唯一版式来源：清空数据单元格 → 写入文本/数值 → 勾选材料与是否返回 → 按锚点（`DK6`/`E26`/`AR26`/`H40`）嵌入四类图片（等比缩放、每类取最早上传的一张）→ 保留模板样式与合并单元格。

**6. 审计**

所有写操作走 `service.Record`，记录操作人、IP、动作、状态前后值、版本、内容；查看/导出也写审计。方案详情里的「评审日志」过滤掉上传/删除附件、查看、导出等干扰项。

**7. DTO 约定（薄 DTO）**

- 请求一律走 `internal/dto` 的 Input 结构，**不直接绑定 GORM 模型**：模型的 `status / version / proposal_no / created_by / source_proposal_id` 等字段绝不由前端决定，`spec / quantity / pallet_parts_qty / write_date` 干脆不在 Input 中定义。
- `ProposalCreateInput`（供应商必填）与 `ProposalUpdateInput`（整表单覆盖）分开，`ProposalFields` 承载公共可编辑字段。
- 入参用 `binding` tag 声明必填、长度、枚举、范围；`bindJSON` 统一转成 `1001` + 中文文案（校验器已注册 JSON 字段名，如「缺少必填项：供应商」）。
- 响应只在**结构与表不一致**时才建 View（`ProposalView / ProposalDetail / Statistics / ContactView / AuditLogView`）；客户、供应商、字典等直接输出模型，不做无意义的套壳。
- 业务校验（提交完整性、以档案为准覆盖快照、派生字段计算）必须留在 service，DTO 只负责形状与格式。

---

## 十一、配置

`backend/configs/config.yaml`（全部支持 `PS_` 环境变量覆盖，见 `backend/.env.example`）：

| 配置 | 说明 | 建议 |
| --- | --- | --- |
| `server.addr` | 监听地址 | `:8090` |
| `server.mode` | `debug` / `release` | 生产 `release` |
| `server.static_dir` | 前端产物目录 | 单二进制部署时设置 |
| `db.*` | MySQL 连接 | 生产用环境变量注入密码 |
| `jwt.secret` | 签名密钥 | 每环境独立，≥32 字节随机串 |
| `jwt.ttl_minutes` | token 有效期 | 120 |
| `jwt.refresh_minutes` | 刷新窗口 | 20160（14 天） |
| `storage.attachments` | 附件根目录 | 需可写 |
| `template.pack` | 导出模板 | 相对 `backend/` 的路径 |
| `cors.allow_origins` | 跨域白名单 | 同域部署留空，不支持 `*` |
| `login.*` | 登录限流 | 5 次 / IP 20 次 / 锁 900 秒 |
| `migrations.dir` | 迁移目录 | `migrations` |

---

## 十二、从 PHP 版本迁移

- 旧表（`pf_user`、`pf_pack_proposal` 等）保留在库中不删；新表为无前缀命名，两套可共存。
- 旧附件仍在仓库根 `storage/attachments/`（已 gitignore），新附件写入 `backend/storage/attachments/`。
- 旧库数据迁移工具（`pf_*` → 新表，含 ULID 映射、`supplier_name` → `supplier_id`）**尚未实现**，属下一阶段任务；当前请用 `-seed` 演示数据或重新导入主数据。
- 旧的 `database/pack_submit.sql` 已删除，初始化统一走 `backend/migrations/`。

---

## 十三、当前实现边界 / 路线图

已完成（第一阶段）：工程骨架、配置与迁移、登录/刷新/改密/限流、动态菜单与权限、方案列表/详情/编辑/提交/撤回/评审/归档/变更/删除、四类图片与附件、Excel 导出、审计日志、供应商联系人、工作台与全量方案列表。

待补（第二阶段）：

1. 主数据管理页面与接口：用户、客户、供应商、零件号、字典的增删改与导入（含「导入零件号自动生成草稿」的受控建单链路）。
2. 「全量详细方案」52 字段明细与导出。
3. 批量修改（预览 + 二次确认）、零件号维护日志页面、主数据维护日志页面。
4. 旧库数据迁移工具。
5. PDF 导出（旧实现为 Dompdf，新方案建议 xlsx → PDF，待定）。

已知限制：

- 登录限流与 token 黑名单为进程内存储，多实例部署需改为共享存储（Redis / DB）。
- 附件上传暂无数量与容量配额，需在反向代理或应用层补充。
- 单实例部署；导出为内存生成，超大并发下建议加限流。
- 客户 / 供应商 / 零件号采用「唯一索引 + 软删」：软删后同名记录仍占用唯一键，第二阶段做主数据删除时需沿用旧方案（删除时改名释放唯一键，如 `原名称#ID`）。

---

## 十四、版本与提交约定

**每次修改代码后必须依次完成**：

1. 递增版本号（前后端独立，按改动范围自动判断）
   ```bash
   node scripts/bump-version.mjs          # 按 git 变更范围递增
   node scripts/bump-version.mjs --all    # 前后端都 +1
   node scripts/bump-version.mjs --backend
   node scripts/bump-version.mjs --frontend
   ```
2. 完整重新构建
   ```bash
   cd backend && go build ./... && go test ./...
   cd ../frontend && npm run build
   ```
3. 重启后端（和前端开发服务）后用 `/api/v1/health` 确认版本生效
4. 提交 Git，**提交信息使用英文**

版本号存放位置（均由脚本生成，勿手工修改）：

| 侧 | 文件 | 字段 |
| --- | --- | --- |
| 后端 | `backend/internal/version/version.go` | `Version`、`ReleasedAt` |
| 前端 | `frontend/src/version.json`、`frontend/package.json` | `version`、`released_at` |

- 后端版本通过 `GET /api/v1/health` 下发，前端版本展示在侧边栏底部。
- 时间统一为北京时间，精确到秒。
- 仅修改 README / 根目录文件时不递增版本号；只改 `backend/**` 只升后端，只改 `frontend/**` 只升前端。
