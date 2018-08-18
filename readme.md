备份后台数据
```
mysqldump -uroot -p -t lv-shop admin_menu admin_permissions admin_role_menu admin_role_permissions admin_role_users admin_roles admin_user_permissions admin_users > database/admin.sql

# 导入数据
mysql -uroot -p lv-shop < database/admin.sql
```
`-t` 选项代表不导出数据表结构，这些表的结构我们会通过 Laravel 的 migration 迁移文件来创建
