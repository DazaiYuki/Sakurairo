# 06 · `$wpdb` 直接查询审计

> 审计日期：2026-09-24
> 方式：全量清点 + 逐条复核，**只读不改**
> 范围：主题内所有 `$wpdb->` 调用（排除内置的 Kirki 与 update-checker）

---

## 一、总体结论

**没有发现 SQL 注入。**

凡涉及用户输入或变量的查询，**全部**使用了 `$wpdb->prepare()`；
其余查询是固定字符串，不含任何变量插值。注入风险 = **0**。

不过清点过程中查出 4 类其他问题（1 个真实 bug、3 类代码债），见下。

---

## 二、调用清点

按方法统计（含注释掉的代码）：

| 方法 | 次数 |
| --- | --- |
| `$wpdb->query()` | 7 |
| `$wpdb->prepare()` | 6 |
| `$wpdb->get_results()` | 6 |
| `$wpdb->insert()` | 5 |
| `$wpdb->get_var()` | 3 |
| `$wpdb->get_row()` | 1 |
| `$wpdb->get_col()` | 1 |

其中相当一部分是**注释掉的死代码**，实际生效的查询远少于此。

---

## 三、逐条复核

### 3.1 已正确处理（无需改动）

| 位置 | 查询 | 判定 |
| --- | --- | --- |
| `functions.php:803-805` | `SELECT COUNT(*) FROM wp_comments WHERE comment_author_email = %s` | ✅ 已用 `prepare` |
| `inc/categories-images.php:156` | `SELECT ID FROM wp_posts WHERE guid = %s` | ✅ 已用 `prepare` |
| `inc/chatgpt/aigc-manage.php:83` | `SELECT * FROM wp_postmeta WHERE post_id = %d AND meta_key = %s` | ✅ 已用 `prepare` |
| `inc/chatgpt/aigc-manage.php:228` | 同上，带 `%d`/`%s` | ✅ 已用 `prepare` |
| `inc/theme-plus.php:663` | `SELECT user_id FROM wp_usermeta WHERE ... meta_value = %s` | ✅ 已用 `prepare` |

### 3.2 静态查询（无注入风险，但有代码债）

| 位置 | 问题 |
| --- | --- |
| `tpl/content-none.php:29` | 用原始 SQL 代替 `WP_Query`；且循环缺 `wp_reset_postdata()`（见 §4） |
| `inc/chatgpt/aigc-manage.php:398-408` | 原始 `INNER JOIN` 代替 `WP_Query` + `meta_query` |
| `inc/chatgpt/aigc-manage.php:857-868` | 同上 |

这三处查询本身**不含变量**，所以没有注入风险。但绕开 `WP_Query` 的代价是：

- 不走对象缓存 / 查询缓存
- 绕过插件钩子（多语言、权限、可见性过滤）
- 需要自行处理 `post_status`、`post_password`、多站点等边界

其中 `aigc-manage.php` 的两处仅在 **ChatGPT 功能的后台页面**用到（该文件由 `inc/chatgpt/chatgpt.php` 引入），
**不影响前台性能**，属低优先级技术债。

### 3.3 死代码

| 位置 | 说明 |
| --- | --- |
| `functions.php:2706` `check_myisam_support()` | **定义了但全主题无任何调用点**。函数体内是 `SHOW ENGINES` |
| `functions.php:2635-2655` | 注释掉的 `comment_markdown` 相关查询 |
| `inc/classes/Cache.php:80-123` | 整段注释掉的 `$wpdb->insert/query`（旧缓存表方案） |

`check_myisam_support()` 值得单独提一句：它执行 `SHOW ENGINES` 且**无结果缓存**。
目前是死代码所以无害，但若将来有人接上调用点，就会变成"每次请求多一次数据库往返"。
建议随 L3 结构重构一并清除。

---

## 四、🔴 真实 bug：`content-none.php` 缺 `wp_reset_postdata()`

### 位置

`tpl/content-none.php:28-36`

```php
$result = $wpdb->get_results("SELECT ID,post_title FROM $wpdb->posts
    where post_status='publish' and post_type='post' ORDER BY ID DESC LIMIT 0 , 20");
foreach ($result as $post) {
    setup_postdata($post);
    $postid = $post->ID;
    $title  = $post->post_title;
    ?>
    <li><a href="<?php echo esc_url(get_permalink($postid)); ?>" ...><?php echo esc_html($title); ?></a></li>
    <?php } ?>
```

**循环结束后没有任何 `wp_reset_postdata()`。**

### 证据

全主题 `setup_postdata()` 的四处调用，只有这一处没有配套复位：

| 位置 | 是否有 `wp_reset_postdata()` |
| --- | --- |
| `search.php:162` | ✅ `search.php:183` |
| `user/page-archive.php:828` | ✅ `user/page-archive.php:835` |
| `inc/classes/Cache.php` | ✅ 第 26、38 行 |
| **`tpl/content-none.php:31`** | ❌ **无** |

### 为什么是 bug

`setup_postdata()` 会改写全局 `$post` 与 `$wp_query->post`。不复位的话，
这个模板之后的所有代码读到的都是**循环里最后一个文章**（而不是主查询的结果）。

后果：

- 搜索无结果页之后的模板（`search.php` 的后续部分、`footer.php`、侧边栏）会看到错误的全局 `$post`
- SEO 插件、面包屑、社交分享等依赖全局 `$post` 的组件可能输出错误的文章信息
- 属**静默错误**：页面上不一定看得出来，但数据是错的

### 附带的第二个问题

查询只 `SELECT ID, post_title` 两列，却把这个**不完整的对象**交给 `setup_postdata()`。
`setup_postdata()` 期望收到完整文章对象 —— 模板标签（如 `the_content()`）在这个对象上会失效。
本处恰好只用到 `ID` 与 `post_title` 所以没暴露，但写法本身是错的。

### 建议改法

```php
$recent = get_posts(array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => 20,
    'orderby'        => 'ID',
    'order'          => 'DESC',
));
foreach ($recent as $post) {
    setup_postdata($post);
    // ...
}
wp_reset_postdata();   // ← 补上
```

换成 `get_posts()` 还顺带修掉了"绕过 WP_Query"的问题。

### 实施与验证（已完成）

改动会影响搜索无结果页的渲染（至少是全局 `$post` 的取值），属行为变更，
必须先给回归集补上这个分支 —— 原本**没有覆盖**它。

执行顺序：

1. 给视觉回归工具补上 `07-search-empty`（搜索无结果）页面，覆盖从 24 张扩到 28 张
2. 用 `git stash` 搁置修复，采一份"未修复"的基准
3. 恢复修复，采集对照
4. 对比结果：**28/28 完全一致，零差异**

同时顺带修掉了工具自身一个更隐蔽的问题：prev/next 卡片的
`<div class="background lazyload" data-src="...">` 走背景图懒加载，
而截图脚本只处理了 `img[data-src]`，漏了这种 div，导致背景图时有时无 ——
这正是此前那个"文章页随机漂移差异"的根因。补上后
**4 轮 × 4 组合连续 16 次对比全部零差异**，工具容差也从 0.15% 恢复为严格 0。

> 也就是说：这个 bug 是**先补测试覆盖、再修**的，而且修的过程还顺带修好了测试工具本身。

---

## 五、优先级建议

| # | 项 | 类型 | 优先级 |
| --- | --- | --- | --- |
| 1 | `content-none.php` 补 `wp_reset_postdata()` | 真实 bug | 中（静默错误，影响面有限） |
| 2 | 三处原始 SQL 改用 `WP_Query` / `get_posts()` | 代码债 | 低 |
| 3 | 清除 `check_myisam_support()` 等死代码 | 代码债 | 低（建议随 L3 一同处理） |

**结论：安全性上无需担心，本次审计的主要产出是发现了 §4 那个静默 bug。**
