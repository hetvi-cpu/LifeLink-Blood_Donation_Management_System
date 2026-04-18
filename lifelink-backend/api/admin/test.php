<?php
echo "PHP works";
require_once '../../config/db_config.php';
echo " | DB connected: " . ($conn ? "YES" : "NO");
?>
```

Then visit: `http://localhost/lifelink-backend/api/admin/test.php`

Tell me what it shows. This will tell us:
- If PHP is running at all
- If the `db_config.php` path is correct
- If the database connection works

Also — just to confirm the exact folder structure, can you verify this path exists?
```
C:\xampp\htdocs\lifelink-backend\config\db_config.php