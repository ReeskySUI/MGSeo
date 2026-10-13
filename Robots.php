<?php

namespace TypechoPlugin\MGSeo;

use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 动态 robots.txt 生成动作。
 */
class Robots extends Widget
{
    /**
     * 输出 robots.txt。
     */
    public function render()
    {
        // 记录爬虫对 robots.txt 的抓取
        Plugin::logSpider();

        $options = Widget::widget('Widget\Options');
        $rules = trim((string) $options->plugin('MGSeo')->robotsRules);

        if ($rules === '') {
            $rules = "User-agent: *\nAllow: /";
        }

        $siteUrl = rtrim($options->siteUrl, '/');
        $robots = $rules . "\n\n";
        $robots .= "Sitemap: {$siteUrl}/sitemap.xml\n";

        $this->response->throwContent(
            $robots,
            'text/plain; charset=' . $options->charset
        );
    }
}
