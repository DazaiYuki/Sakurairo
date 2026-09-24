# 09 · WordPress 区块样式加载机制 与 PJAX 边界修复

> 检查日期：2026-09-24
> 触发问题：用户要求"细说 WP 按需加载"，并明确"PJAX 不管开不开都不要有错误"
> 结论：**发现并修复了一个真实缺陷 —— PJAX 关闭时引用块左边框等"区块设计样式"会丢失**
> 同时查清了此前文档 07 的一处归因错误

---

## 一、WordPress 7.x 的区块样式是分两层的

这是理解全部问题的前提。WP 会为每个核心区块准备两个样式文件：

| 层 | 路径 | 内容 | 示例（引用块） |
| --- | --- | --- | --- |
| **style** | `wp-includes/blocks/{name}/style.min.css` | 基础布局 | `box-sizing` / `padding` / `cite` 对齐 |
| **theme** | `wp-includes/blocks/{name}/theme.min.css` | **设计样式** | **`border-left: .25em solid`（左侧竖线）** |

**引用块的左边框只存在于 `theme.css`**，`style.css` 里 `border-left` 出现 0 次。
实测两份文件：

```
blocks/quote/style.min.css   699 字节   border-left: 0 处
blocks/quote/theme.min.css   492 字节   border-left: 2 处
  .wp-block-quote{border-left:.25em solid;margin:0 0 1.75em;padding-left:1em}
```

对应到合并文件（体积差了两个数量级）：

| 合并文件 | 路径 | 体积 |
| --- | --- | --- |
| `wp-block-library` | `css/dist/block-library/style.min.css` | **140,829 字节（137 KB）** |
| `wp-block-library-theme` | `css/dist/block-library/theme.min.css` | **2,752 字节（2.7 KB）** |

> **记住这个比例：137 KB : 2.7 KB。** 后面所有方案都围绕它展开。

---

## 二、两种加载模式

由 `wp_should_load_block_assets_on_demand()` 决定（WP 7.x 的函数名，
`wp_should_load_separate_core_block_assets()` 是它的上游开关）：

### 模式 A：非按需（合并加载）

```php
// script-loader.php → wp_enqueue_registered_block_scripts_and_styles()
if ( wp_should_load_block_assets_on_demand() ) { ...return; }   // 按需则直接退出

foreach ( $block_registry->get_all_registered() as $block_name => $block_type ) {
    foreach ( $block_type->style_handles as $style_handle ) {
        wp_enqueue_style( $style_handle );      // 所有区块，一个不落
    }
}
```

→ 页面拿到合并的 `style.min.css`（137 KB）

### 模式 B：按需加载

```php
// script-loader.php → wp_enqueue_block_style()
if ( wp_should_load_block_assets_on_demand() ) {
    $callback_separate = function ( $content, $block ) use ( $block_name, $callback ) {
        if ( $block_name === $block['blockName'] ) {
            return $callback( $content );        // 渲染到该区块时才 enqueue 它的样式
        }
        return $content;
    };
    add_filter( 'render_block', $callback_separate, 10, 2 );
}
```

→ 只有**页面实际用到的**区块样式被 enqueue，且由 WP 输出为
**内联 `<style id="wp-block-xxx-inline-css">`**，而不是 `<link>`。

> ⚠️ **这一点极其容易误判。** 用 `grep '<link.*block-library'` 检查会得出
> "区块样式没加载"的错误结论 —— 实际它们以内联 `<style>` 形式存在。
> 本项目文档 07 就犯过这个错误（见 §五）。

---

## 三、关键的不对称：theme.css 不参与按需加载

`theme.css` 在整个 WP 核心中只有一处 enqueue：

```php
// script-loader.php → wp_common_block_scripts_and_styles()
wp_enqueue_style( 'wp-block-library' );

if ( current_theme_supports( 'wp-block-styles' ) && ! wp_should_load_separate_core_block_assets() ) {
    wp_enqueue_style( 'wp-block-library-theme' );
}
```

**注意那个 `! `：按需模式下 `theme.css` 不会被加载。**

而按需加载的 `render_block` 机制**只处理 `style.css`**，不涉及 `theme.css`。

**所以按需模式下，"基础布局"有了，"设计样式"没有 —— 引用块的竖线边框就此消失。**

这是 core 的有意设计（`theme.css` 被视为"主题设计层"，应由主题负责），
但对不做处理的主题来说就是一个坑。

---

## 四、Sakurairo 的实际情况

### 4.1 主题没有声明 `wp-block-styles`

全主题搜索 `wp-block-styles` —— **0 处**。

所以 WP 那条 `theme.css` 的 enqueue 条件**永远不成立**。

### 4.2 边框是主题自己显式加载的

```php
// functions.php:658-668
add_action('wp_enqueue_scripts', function () {
    if (iro_opt("poi_pjax", true) != true) {
        return;                    // ← PJAX 关闭时直接退出
    }
    wp_enqueue_style( 'wp-block-library' );
    wp_enqueue_style( 'wp-block-library-theme' );      // ← 边框来源
    wp_enqueue_style( 'wp-block-library-comments' );
    wp_enqueue_style( 'wp-block-library-widgets' );
}, 5);
```

以及：

```php
// functions.php:638-646
add_action("after_setup_theme", function () {
    if (iro_opt("poi_pjax", true) == true) {           // ← 只在 PJAX 开时
        add_filter( 'wp_should_load_separate_core_block_assets', '__return_false' );
        add_filter( 'should_load_separate_core_block_assets', '__return_false', 1 );
        add_filter( 'should_load_block_assets_on_demand', '__return_false', 1 );
        add_filter( 'enqueue_empty_block_content_assets', '__return_true' );
    }
});
```

**主题的意图很清楚**：PJAX 用 AJAX 切换页面内容，服务端无法预知目标页用了哪些区块，
所以干脆全量加载。这是合理的权衡。

### 4.3 但 PJAX 关闭时没人管

PJAX 关 → 主题两条 hook 都直接 return → 走 WP 核心默认。

而核心默认是 **按需加载**（`should_load_separate_core_block_assets` 的默认参数是
`false`，但核心在优先级 0 注册了 `__return_true`，主题不干预时最终为 **true**）。

**于是：按需加载生效，`theme.css` 无人加载，引用块边框丢失。**

---

## 五、实测结果（这也修正了文档 07 的一个错误结论）

| 场景 | separate | style.css | theme.css | 引用块边框 |
| --- | --- | --- | --- | --- |
| **PJAX 开**（主题默认） | `false` | 合并加载 137 KB | ✅ 主题显式 enqueue | ✅ 正常 |
| **PJAX 关** | `true`（核心默认） | 按需内联 | ❌ **未加载** | ❌ **丢失** |

实测方式与证据：

```
PJAX 开：<link ...block-library/style.min.css> 存在
         内联块含 wp-block-library-theme

PJAX 关：无 style.min.css 链接
         内联块 = classic-theme-styles, global-styles,
                  wp-block-heading, wp-block-library, wp-block-list,
                  wp-block-paragraph, wp-block-quote, wp-img-auto-sizes-contain
         含 theme.min.css: False
```

截图对比（文章页引用块区域）：PJAX 开有左侧竖线；PJAX 关竖线消失、引用文字居中。

### 对文档 07 的修正

文档 07 当时写：

> 按需加载在 Sakurairo 上**几乎失效**……实验状态下文章页只加载了
> `block-library/common.min.css`，而正文里 11 处 `wp-block-quote` 对应的样式**一个都没加载**。

**这句是错的。** 错误原因是只检查了 `<link>` 标签，漏掉了内联 `<style>`。
实际情况是：

- `wp-block-quote` 的 **style.css 按需内联加载了**（`wp-block-quote-inline-css`，779 字符）
- 丢失的是 **`theme.css`**（含 `border-left`）

**"按需加载失效"这个归因不成立；真因是"按需模式不加载 theme.css"。**
边框会丢这个结论本身是对的，但机制说错了 —— 这直接影响方案设计。

---

## 六、实施方案（已落地）

**目标**：PJAX 开/关都正确，且不开 PJAX 时省下那 137 KB。

```php
// functions.php → after_setup_theme
if ( iro_opt( 'poi_pjax', true ) ) {
    // PJAX：AJAX 切页预知不到目标页的区块，保持全量加载
    add_filter( 'wp_should_load_separate_core_block_assets', '__return_false' );
    add_filter( 'should_load_separate_core_block_assets', '__return_false', 1 );
    add_filter( 'should_load_block_assets_on_demand', '__return_false', 1 );
    add_filter( 'enqueue_empty_block_content_assets', '__return_true' );
} else {
    // 非 PJAX：显式开启按需加载（核心默认即为 true，写明以防被其它插件改掉）
    add_filter( 'wp_should_load_separate_core_block_assets', '__return_true' );
}

// functions.php → wp_enqueue_scripts（优先级 5）
if ( iro_opt( 'poi_pjax', true ) == true ) {
    wp_enqueue_style( 'wp-block-library' );
    wp_enqueue_style( 'wp-block-library-theme' );
    wp_enqueue_style( 'wp-block-library-comments' );
    wp_enqueue_style( 'wp-block-library-widgets' );
    return;
}
// 非 PJAX：区块样式按需加载，但必须单独补 theme.css（2.7 KB，保住引用块边框）
wp_enqueue_style( 'wp-block-library-theme' );
```

### 关键点

**必须保留 `wp-block-library-theme`**，这是整个改动里唯一容易漏、后果又明显的一步。
它提供的不只是那条竖线 —— `theme.css` 里还有 `margin: 0 0 1.75em`、`padding-left: 1em`，
**缺失会连带改变布局**：实测文章页高度会差 **12 px**（桌面）/ **24 px**（移动）。

---

## 七、验证结果

### 7.1 PJAX 开启：行为完全不变

| 检查项 | 结果 |
| --- | --- |
| `block-library/style.min.css` | ✅ 仍加载 |
| `wp-block-library-theme` | ✅ 仍加载 |
| PHP 日志 | 0 条 |

### 7.2 PJAX 关闭：样式完整且省体积

| 检查项 | 改动前 | 改动后 |
| --- | --- | --- |
| `block-library/style.min.css` | 加载（按需模式下本应不加载） | ✅ **不加载** |
| `theme.css` | ❌ 缺失 | ✅ 加载（内联 `wp-block-library-theme`） |
| `border-left:.25em solid` | ❌ 无 | ✅ 有 |

**体积对照（文章页，本地实测）：**

| | 外链 CSS | 内联 CSS | 总计 |
| --- | --- | --- | --- |
| PJAX 开（全量） | 362.6 KB | 18.6 KB | **381.2 KB** |
| PJAX 关（按需 + theme） | 225.0 KB | 24.8 KB | **249.8 KB** |
| **差值** | **−137.5 KB** | +6.2 KB | **−131.4 KB（−34.5%）** |

### 7.3 视觉回归

**PJAX 关 vs 基准（PJAX 开）：27/28 完全一致。**

唯一那张 `07-search-empty` 经查为**采集抖动**，与本次改动无关：

- 单页重复采集 5 次，两两对比**全部一致**
- 该页面排在采集顺序最后（第 7 个），连续采集时会受前序页面状态影响
- 两次完整采集各出现 1 张，且配色随机漂移（一次 light、一次 dark），符合抖动特征

> 这是视觉回归工具自身的已知局限，记录在 `env/tools/README.md`。
> 判断方法：出现 1 张差异时先重采，位置随机漂移即为噪声。

**引用块边框已截图确认恢复。**

### 7.4 日常动效

未改动任何组件级规则，hover / 抽屉 / 折叠面板动效保持原样。

---

## 八、后续可选项（未做）

1. **PJAX 开启时能否也省这 137 KB？**
   需要在 PJAX 的 AJAX 响应里带上目标页的区块样式，并处理去重与注入时机。
   属结构改动，风险高一档，本轮不做。

2. **是否补声明 `add_theme_support('wp-block-styles')`？**
   补了以后 WP 会在非按需模式下自动 enqueue `theme.css`，主题那 4 条可以简化。
   但要先确认与主题现有显式 enqueue 不会重复。
