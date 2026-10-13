<?php

namespace TypechoPlugin\MGSeo;

use Typecho\Common;
use Typecho\Db;
use Typecho\Router;
use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 动态站点地图生成动作。
 */
class Sitemap extends Widget
{
    /**
     * 输出 sitemap.xml。
     */
    public function render()
    {
        // 记录爬虫对 sitemap 的抓取
        Plugin::logSpider();

        $db = Db::get();
        $options = Widget::widget('Widget\Options');

        $config = $options->plugin('MGSeo');
        $blocks = $config->sitemapBlocks;
        if (!is_array($blocks)) {
            $blocks = ['posts', 'pages', 'categories', 'tags'];
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // 首页
        $xml .= $this->urlNode($options->siteUrl, '1.0', 'daily', null);

        // 每个分类/标签下最新文章的修改时间（一条聚合查询）
        // 注意：MAX(...) 内部的 table. 不会被查询构造器替换前缀，需显式使用真实前缀
        $contentsTable = $db->getPrefix() . 'contents';
        $lastmodMap = [];
        $lastmodRows = $db->fetchAll(
            $db->select('table.relationships.mid', ["MAX({$contentsTable}.modified)" => 'lastmod'])
                ->from('table.contents')
                ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.type = ?', 'post')
                ->group('table.relationships.mid')
        );
        foreach ($lastmodRows as $row) {
            $lastmodMap[$row['mid']] = $row['lastmod'];
        }

        if (in_array('pages', $blocks)) {
            $pages = $db->fetchAll(
                $db->select()->from('table.contents')
                    ->where('table.contents.status = ?', 'publish')
                    ->where('table.contents.created < ?', $options->gmtTime)
                    ->where('table.contents.type = ?', 'page')
                    ->order('table.contents.created', Db::SORT_DESC)
            );
            foreach ($pages as $page) {
                $xml .= $this->urlNode(
                    $this->permalink($page, 'page'),
                    '0.5',
                    'monthly',
                    date('c', $page['modified'])
                );
            }
        }

        if (in_array('posts', $blocks)) {
            $posts = $db->fetchAll(
                $db->select()->from('table.contents')
                    ->where('table.contents.status = ?', 'publish')
                    ->where('table.contents.created < ?', $options->gmtTime)
                    ->where('table.contents.type = ?', 'post')
                    ->order('table.contents.created', Db::SORT_DESC)
            );
            foreach ($posts as $post) {
                $xml .= $this->urlNode(
                    $this->permalink($post, 'post'),
                    '0.8',
                    'weekly',
                    date('c', $post['modified'])
                );
            }
        }

        if (in_array('categories', $blocks)) {
            $categories = $db->fetchAll(
                $db->select()->from('table.metas')
                    ->where('table.metas.type = ?', 'category')
                    ->order('table.metas.mid', Db::SORT_DESC)
            );
            foreach ($categories as $category) {
                $lastmod = isset($lastmodMap[$category['mid']])
                    ? date('c', $lastmodMap[$category['mid']]) : null;
                $xml .= $this->urlNode(
                    $this->permalink($category, 'category'),
                    '0.6',
                    'daily',
                    $lastmod
                );
            }
        }

        if (in_array('tags', $blocks)) {
            $tags = $db->fetchAll(
                $db->select()->from('table.metas')
                    ->where('table.metas.type = ?', 'tag')
                    ->order('table.metas.mid', Db::SORT_DESC)
            );
            foreach ($tags as $tag) {
                // 无文章的标签不输出
                if (!isset($lastmodMap[$tag['mid']])) {
                    continue;
                }
                $xml .= $this->urlNode(
                    $this->permalink($tag, 'tag'),
                    '0.4',
                    'weekly',
                    date('c', $lastmodMap[$tag['mid']])
                );
            }
        }

        $xml .= '</urlset>';

        $this->response->throwContent($xml, 'application/xml');
    }

    /**
     * 构造一个 URL 节点。
     *
     * @param string $loc
     * @param string $priority
     * @param string $changefreq
     * @param string|null $lastmod
     * @return string
     */
    private function urlNode($loc, $priority, $changefreq, $lastmod)
    {
        $node = "\t<url>\n";
        $node .= "\t\t<loc>" . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
        if (null !== $lastmod) {
            $node .= "\t\t<lastmod>{$lastmod}</lastmod>\n";
        }
        $node .= "\t\t<changefreq>{$changefreq}</changefreq>\n";
        $node .= "\t\t<priority>{$priority}</priority>\n";
        $node .= "\t</url>\n";

        return $node;
    }

    /**
     * 根据内容行与路由名生成永久链接。
     *
     * @param array $row
     * @param string $type
     * @return string
     */
    private function permalink(array $row, $type)
    {
        $options = Widget::widget('Widget\Options');
        $routeExists = (null !== Router::get($type));
        $row['pathinfo'] = $routeExists ? Router::url($type, $row) : '#';

        return Common::url($row['pathinfo'], $options->index);
    }
}
