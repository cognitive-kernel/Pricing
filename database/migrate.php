<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
$pdo=Database::connection();
$statements=[
"IF COL_LENGTH('dbo.products','min_sources') IS NULL ALTER TABLE dbo.products ADD min_sources TINYINT NOT NULL CONSTRAINT DF_products_min_sources DEFAULT 2",
"IF COL_LENGTH('dbo.products','max_sources') IS NULL ALTER TABLE dbo.products ADD max_sources TINYINT NOT NULL CONSTRAINT DF_products_max_sources DEFAULT 3",
"IF COL_LENGTH('dbo.products','status') IS NULL ALTER TABLE dbo.products ADD status NVARCHAR(30) NOT NULL CONSTRAINT DF_products_status DEFAULT 'active'",
"IF OBJECT_ID('dbo.product_categories','U') IS NULL CREATE TABLE dbo.product_categories(id INT IDENTITY PRIMARY KEY,name NVARCHAR(150) NOT NULL UNIQUE,description NVARCHAR(500) NULL,is_active BIT NOT NULL DEFAULT 1,created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),updated_at DATETIME2 NOT NULL DEFAULT SYSDATETIME())",
"IF COL_LENGTH('dbo.products','category_id') IS NULL ALTER TABLE dbo.products ADD category_id INT NULL",
"IF NOT EXISTS(SELECT 1 FROM sys.foreign_keys WHERE name='FK_products_category') ALTER TABLE dbo.products ADD CONSTRAINT FK_products_category FOREIGN KEY(category_id) REFERENCES dbo.product_categories(id)",
"IF OBJECT_ID('dbo.price_queue','U') IS NULL CREATE TABLE dbo.price_queue(id BIGINT IDENTITY PRIMARY KEY,product_id INT NOT NULL UNIQUE,score DECIMAL(10,2) NOT NULL,priority_level NVARCHAR(30) NOT NULL,reason NVARCHAR(2000) NULL,status NVARCHAR(30) NOT NULL DEFAULT 'new',assigned_to INT NULL,due_at DATETIME2 NULL,started_at DATETIME2 NULL,completed_at DATETIME2 NULL,result_note NVARCHAR(2000) NULL,created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),updated_at DATETIME2 NOT NULL DEFAULT SYSDATETIME())",
"IF COL_LENGTH('dbo.price_queue','assigned_to') IS NULL ALTER TABLE dbo.price_queue ADD assigned_to INT NULL",
"IF COL_LENGTH('dbo.price_queue','due_at') IS NULL ALTER TABLE dbo.price_queue ADD due_at DATETIME2 NULL",
"IF COL_LENGTH('dbo.price_queue','started_at') IS NULL ALTER TABLE dbo.price_queue ADD started_at DATETIME2 NULL",
"IF COL_LENGTH('dbo.price_queue','completed_at') IS NULL ALTER TABLE dbo.price_queue ADD completed_at DATETIME2 NULL",
"IF COL_LENGTH('dbo.price_queue','result_note') IS NULL ALTER TABLE dbo.price_queue ADD result_note NVARCHAR(2000) NULL",
"IF OBJECT_ID('dbo.price_workflow','U') IS NULL CREATE TABLE dbo.price_workflow(id BIGINT IDENTITY PRIMARY KEY,product_id INT NOT NULL,step_id INT NOT NULL,assigned_to INT NULL,status NVARCHAR(30) NOT NULL DEFAULT 'pending',comment NVARCHAR(2000) NULL,started_at DATETIME2 NULL,completed_at DATETIME2 NULL,created_at DATETIME2 NOT NULL DEFAULT SYSDATETIME(),updated_at DATETIME2 NOT NULL DEFAULT SYSDATETIME())"
];
foreach($statements as $sql)$pdo->exec($sql);
echo "Migration completed.".PHP_EOL;
