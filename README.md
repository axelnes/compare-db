# COMPARE DB

## Description
This script compares the structure of two MySQL databases. It is useful when one database has been further developed, but you do not want to replace the original. Instead, it allows you to adopt only the structural changes from the advanced database.

## Features
- **Flag `$aend=0`:** Outputs the differences between the two databases as an array.  
- **Flag `$aend=1`:** Applies the differences directly to the first database.  

## Requirements
- PHP 7.4 or higher
- MySQL/MariaDB
- PDO extension enabled in PHP

## Installation
1. Clone or download this repository.
2. Configure the database connection settings in the script:
   ```php
   $db1_host = '[host]';
   $db1_db = '[database 1]';
   $db1_user = '[User database 1]';
   $db1_password = '[Password database 1]';

   $db2_host = '[host]';
   $db2_db = '[database 2]';
   $db2_user = '[User database 2]';
   $db2_password = '[Password database 2]';
