# 05 · Phase 1 改动记录（低风险高收益）

> 执行日期：2026-09-24
> 分支：`relive`
> 原则：外观零变更。每一项都经视觉回归验证，独立提交、可单独回退。

---

## 总览

| # | 提交 | 改动 | 验证方式 | 视觉影响 |
| --- | --- | --- | --- | --- |
| 1 | `a9a77339` | `X-Frame-Options` 从模板移到 `send_headers` | 响应头实测 + 日志警告清零 + 视觉回归 | 无 |
| 2 | `b97de58e` | 移除主题 CSS 的冗余 preload 声明 | 标签计数 + 视觉回归 | 无 |
| 3 | `6dbedbf3` | 区块样式表 enqueue 移到正确钩子 | 样式表顺序逐条比对 + 日志清零 + 视觉回归 | 无 |
| 4 | `4686566e` | 清理 CSS 行尾双分号（7 处） | 计数 + diff 复核 + 视觉回归 | 无 |

**累计效果：每次页面请求的 PHP 告警从 4 条降到 0 条。**

---

## 1 · `X-Frame-Options` 移出模板（`a9a77339`）

### 问题

`header.php:44` 在模板顶层调用 `header('X-Frame-Options: SAMEORIGIN')`。
模板渲染时输出往往已经开始，响应头被锁定，`header()` **静默失败**。

### 实测证据

```
PHP Warning: Cannot modify header information - headers already sent
by (output started at wp-includes/functions.php:6260)
in D:\Sakurairo-AI\Sakurairo\header.php on line 44
```

触发链：错误钩子 enqueue 样式 → WordPress 输出 `_doing_it_wrong` 提示 → 输出开始 → `header()` 失败。

### 为什么这个问题重要

`X-Frame-Options` 是防点击劫持的响应头。**发不出去不会报错、不会提示，用户完全无感**。
线上之所以没暴露，只是因为生产环境 `WP_DEBUG` 关闭、提示不打印 —— 属**依赖巧合才能工作**。
任何插件提前输出、或文件带 BOM / 结尾空行，防护就会失效。

### 改动

- `header.php`：删除第 44 行（其余三个变量赋值保持不变）
- `functions.php`：新增 `iro_send_security_headers()` 挂到 `send_headers`，
  作用域与原行为一致（仅前台），并加 `headers_sent()` 兜底

### 验证

| 项 | 结果 |
| --- | --- |
| `php -l` | 无语法错误 |
| 首页响应头 | 含 `X-Frame-Options: SAMEORIGIN` |
| 文章页响应头 | 含 `X-Frame-Options: SAMEORIGIN` |
| 日志中 `headers already sent` | 2 条 → **0 条** |
| 视觉回归 | 24/24 通过 |

---

## 2 · 移除冗余的 preload 声明（`b97de58e`）

### 问题

`functions.php` 的 `wp_head` 回调同时输出两条指向**同一 URL** 的标签：

```php
echo '<link rel="preload" href="' .$iro_css. '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
echo '<link rel="stylesheet" href="' . $iro_css . '">';
```

第 1 条的本意是让 CSS **异步加载**（不阻塞渲染），但第 2 条是阻塞式样式表 ——
渲染仍要等它，异步意图被完全抵消；同一 URL 还会被当作两个样式表重复应用。

### 改动

只保留阻塞式样式表，删除冗余的 preload 行。

**为什么不顺便实现异步化？** 异步化会引入 FOUC（首屏无样式闪烁），属于视觉变更，
超出本次「外观零变更」的范围。要做也应该作为一个独立的、需要专门验证的改动。

### 验证

| 项 | 结果 |
| --- | --- |
| `php -l` | 无语法错误 |
| 指向主题 CSS 的 preload 标签 | 1 条 → **0 条** |
| 样式表标签 | 正常保留 |
| 视觉回归 | 24/24 通过 |

---

## 3 · 区块样式表 enqueue 移到正确钩子（`6dbedbf3`）

### 问题

`functions.php` 在 `after_setup_theme` 里调用 `wp_enqueue_style` 注册 4 个区块样式表，
被 WordPress 判定为用法错误：

```
Function wp_enqueue_style was called incorrectly. Scripts and styles should not be
registered or enqueued until the wp_enqueue_scripts, admin_enqueue_scripts, or
login_enqueue_scripts hooks. This message was added in version 3.3.0.
Triggered by: wp-block-library / wp-block-library-theme /
              wp-block-library-comments / wp-block-library-widgets
```

**每次页面请求固定 4 条**，24 次页面加载累计 100 条。

### 这里的风险点

钩子位置变化**可能改变样式表在输出中的先后顺序**，进而改变层叠关系 —— 那就是视觉变更。
这是本项改动唯一有风险的地方，必须专门验证。

### 改动

- 关闭「区块资源按需加载」的 4 个 filter 拆到独立回调，仍留在 `after_setup_theme`
  （它们是 filter，注册时机不影响结果）
- 4 条 `wp_enqueue_style` 移到 `wp_enqueue_scripts` 回调，**优先级 5**
  —— 取 5 是为了早于主题自身的 `sakura_scripts`（优先级 10），
  从而保持这些样式表在最终输出里的先后顺序不变

### 验证

样式表输出顺序逐条比对（改动前用 `git stash` 临时还原后抓取）：

```
font-awesome 6.7.2  →  wp-block-library  →  主题 CSS  →  Google Fonts
```

**改动前后完全一致。**

| 项 | 结果 |
| --- | --- |
| `php -l` | 无语法错误 |
| 样式表顺序 | 逐条一致 |
| 日志条目 | 100 → **0**（无任何告警） |
| 视觉回归 | 24/24 通过 |

---

## 4 · 清理 CSS 行尾双分号（`4686566e`）

7 处声明以 `;;` 结尾（`style.css` 2 处、`css/dark.css` 5 处）。
CSS 会把多出的分号当作空声明解析，不影响渲染，属语法毛刺。

改动为**每行删除行尾 1 个字符**，`git diff` 已逐条复核：

```
style.css:22-23      transition / -webkit-transition
css/dark.css:38      color
css/dark.css:92,106  box-shadow
css/dark.css:102     background
css/dark.css:524     box-shadow
```

验证：双分号计数 `2→0` / `5→0`；视觉回归 24/24 通过。

---

## 视觉回归工具的增强

本轮同时改进并**修正**了回归工具（详见 `04-视觉回归工具搭建记录.md`）：

1. **修复一个被低估的抖动源**：导航栏文章标题（`.nav-article-title`）由 `nav.js`
   通过 `[data-scrollswap]` 与内联 opacity 切换显隐，全页截图的视口 resize 会异步触发它，
   时序不稳、**复现率约 60%**。此前那轮"24/24"其实带着这个潜在误报。
2. **稳定性签名加入页面总高度与未完成图片数**，用于捕捉"位移型"差异。
3. 增加 `ONLY_PAGE` / `ONLY_VIEWPORT` / `ONLY_THEME` 环境变量，可只跑子集快速迭代
   （单张约 10 秒 vs 全量约 2.5 分钟）。
4. 新增 `probe.js` 元素探测、`crop.js` 支持横向范围。
5. 默认容差设为 **0.15%**，并如实说明噪声底噪：
   文章页截图的 prev/next 卡片区域存在 1~2px 亚像素残留偏移（0.02%~0.14%，位置随机漂移），
   与代码无关。报告中**始终**列出所有非零条目及实际差异率，不会静默吞掉信息。

> **重要提醒**：出现 1~2 张差异时，先**重采一次**确认。若标记位置变化，即为噪声；
> 若稳定复现同一位置且幅度明显更大，才是真实回归。

---

## 未做的项及原因

| 项 | 原因 |
| --- | --- |
| 收敛全局 `* { transition: all }` | **会改变动效行为**（不属于"静态外观"），而截图冻结了过渡，视觉回归**无法验证**此项。属于需要单独设计验证方式的改动，不在本轮零风险批次内 |
| 主题内 `error_reporting` / `ini_set` | 与用户可见的 `php_notice_filter` 选项绑定，移除等于取消一个功能，需要你决定保留还是废除此选项 |
| `js/*.map` 清理 | 这些文件由外部仓库 `Fuukei/Sakurairo_Scripts` 经 CI 同步进来，删了会被下次同步加回。需先确定 JS 源码策略（见文档 01 的决策点 D1） |

---

## 下一步建议

Phase 2 候选（按收益排序）：

1. **主题 CSS 静态化** —— 当前由 `css/index.php` 用 `preg_replace` 运行时拼接，
   响应实测 2.30s，且无法上 CDN。**这是主题侧最大的单项性能问题**
2. 恢复区块样式按需加载（当前强制全量加载，线上合并包内含 1851 条 `wp-block-*` 规则）
3. `$wpdb` 33 处查询复核
4. 内联 `<script>` / `<style>` 外链化
5. 评估移除 `polyfill.js`

---

*本轮共 4 个提交，主题代码改动均为最小必要范围，每项可单独 `git revert`。*
