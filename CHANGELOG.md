# 更新日志

本项目遵循语义化版本（SemVer），兼容 Typecho 1.3.0+。

## 1.1.1 - 2026-10-23

### 问题修复

- 蜘蛛日志表 `time` 字段补充索引，提升按时间清理与时间范围查询性能；旧库启用时自动幂等补建，新库在 DDL 中直接创建。
- 兼容 MySQL、PostgreSQL、SQLite 三种数据库：PostgreSQL/SQLite 使用 `CREATE INDEX IF NOT EXISTS`，MySQL 捕获重复建索引异常，均不阻断插件启用。

### 工程规范

- 新增 `CHANGELOG.md` 与 `.gitattributes`（统一 LF 换行）。

## 1.1.0 - 2026-10-13

### 新增功能

- 首个正式版本：动态站点地图（`/sitemap.xml`）、动态 robots.txt（`/robots.txt`）、蜘蛛日志三合一。
- 合并原 MGRobots 与 Sitemap 两个插件的能力，不写静态文件，适配 Docker 部署。
- 自动识别 Google、Bing、Baidu、Sogou、Yandex、DuckDuckGo、字节、360、Apple、神马爬虫，日志保留最近 30 天。
- 兼容 MySQL、PostgreSQL、SQLite 全部数据库。
