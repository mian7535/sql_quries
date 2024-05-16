<?php
require 'vendor/autoload.php';
require 'includes/database/connection.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

// Initialize PHPExcel Reader
$reader = new Xlsx();

// Define Excel file path
$excelFilePath = 'includes/data/excel_data.xlsx';

// Initialize ZipArchive
$zip = new ZipArchive();

// Array to store sheet names
$sheetNames = [];

// Attempt to open the Excel file
if ($zip->open($excelFilePath) === TRUE) {
    // Read workbook XML
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    
    if ($workbookXml !== FALSE) {
        // Load workbook XML
        $xml = simplexml_load_string($workbookXml);
        
        if ($xml !== FALSE) {
            // Register XML namespace
            $xml->registerXPathNamespace('ns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            
            // Retrieve sheet names
            foreach ($xml->xpath('//ns:sheet') as $sheet) {
                $sheetNames[] = (string)$sheet['name'];
            }
        } else {
            echo "Failed to parse the workbook XML.";
        }
    } else {
        echo "Failed to read the workbook XML from the archive.";
    }
    $zip->close();
} else {
    echo "Failed to open the Excel file.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retrieve Sheet Data</title>
    <link rel="stylesheet" href="includes/css/style.css">
</head>
<body>

<h2>Select a Sheet</h2>
<form method="post">
    <label for="selectedSheet">Select a sheet:</label>
    <select name="selectedSheet" id="selectedSheet">
        <?php
        foreach ($sheetNames as $name) {
            echo "<option value=\"$name\">$name</option>";
        }
        ?>
    </select>
    <button type="submit">Retrieve Data</button>
    <button type="submit" name="refresh">Refresh</button>
    <button type="submit" name="join_query">Join Query</button>
    <button type="submit" name="csv">Get CSV</button>

</form>
<?php

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if the 'refresh' button is clicked
    if (isset($_POST['refresh'])) {
        // Handle refresh if needed
        echo ""; 
    }else if (isset($_POST['csv'])) {
          csv($conn);
    }else if (isset($_POST['join_query'])) {

        createTableFromJoinQuery($conn);

    }else if (isset($_POST['selectedSheet'])) {
        // Get the selected sheet name
        $selectedSheet = $_POST['selectedSheet'];
        echo "<h3>Sheet Data for $selectedSheet</h3>";
        
        // Load the selected sheet from the Excel file
        $reader->setLoadSheetsOnly([$selectedSheet]); 
        $spreadsheet = $reader->load($excelFilePath);
        $worksheet = $spreadsheet->getSheetByName($selectedSheet);

        // Display form to add fields to the database
        echo "<form method='post' action=''>";
        echo "<input type='hidden' name='dbselectedSheet' value='$selectedSheet' />";
        echo "<button type='submit' name='add_fields_to_db'>Add Fields to Database</button>";
        echo "</form>";

        // Display sheet data in a table
        echo "<table>";
        $rowNumber = 1;
        foreach ($worksheet->getRowIterator() as $row) {
            echo "<tr>";
            echo "<td>$rowNumber</td>";
            foreach ($row->getCellIterator() as $cell) {
                echo "<td>" . $cell->getValue() . "</td>";
            }
            echo "</tr>";
            $rowNumber++;
        }
        echo "</table>";
    }

    // Check if 'add_fields_to_db' button is clicked
    if (isset($_POST['add_fields_to_db'])) {
        // Retrieve selected sheet
        $selectedSheet = $_POST['dbselectedSheet'];
        $reader->setLoadSheetsOnly([$selectedSheet]); 
        $spreadsheet = $reader->load($excelFilePath);
        $worksheet = $spreadsheet->getSheetByName($selectedSheet);

        // Create table if not exists
        $tableName = str_replace(' ', '_', strtolower($selectedSheet)); // Adjust table name if needed
        
        // Check if the table exists
        $checkTableSql = "SHOW TABLES LIKE '$tableName'";
        $tableExists = $conn->query($checkTableSql)->num_rows > 0;

        if (!$tableExists) {
            // If the table doesn't exist, create it with a primary key named 'id'
            $sql = "CREATE TABLE `$tableName` (
                `id` INT AUTO_INCREMENT PRIMARY KEY
            )";
            
            if ($conn->query($sql) !== TRUE) {
                echo "Error creating table: " . $conn->error;
            }
            echo "Table '$tableName' created successfully.<br>";
        }

        // Extract column names from the second row
        $columnNames = [];
        $isFirstRow = true;
        foreach ($worksheet->getRowIterator() as $row) {
            if ($isFirstRow) {
                foreach ($row->getCellIterator() as $cell) {
                    $columnName = trim($cell->getValue());
                    // Check if the column name already exists
                    if (!isset($columnNames[$columnName])) {
                        $columnNames[$columnName] = true; // Mark the column name as seen
                    } else {
                        echo "Duplicate column name '$columnName' found. Skipping...<br>";
                    }
                }
                $isFirstRow = false;
                continue;
            }
            break;
        }

        // Extract unique column names
        $uniqueColumnNames = array_keys($columnNames);
        
// Check if columns exist in the table, and add them if they don't
$isFirstColumn = true;
foreach ($uniqueColumnNames as $columnName) {
    $checkColumnSql = "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'";
    $columnExists = $conn->query($checkColumnSql)->num_rows > 0;
    if (!$columnExists) {
        // If column does not exist, add it to the table
        $alterTableSql = "ALTER TABLE `$tableName` ADD `$columnName` TEXT";
        if ($conn->query($alterTableSql) !== TRUE) {
            echo "Error adding column '$columnName' to table: " . $conn->error;
        }
        echo "Column '$columnName' added to table '$tableName'.<br>";
    } else {
        echo "Column '$columnName' already exists in table '$tableName'. Moving to the next one.<br>";
    }

    // Rename the column to "item" only if it's the first column and 'item' column doesn't exist
    if ($isFirstColumn && !$columnExists) {
            // Change the data type of the column to VARCHAR(255)
    $changeDataTypeSql = "ALTER TABLE `$tableName` MODIFY COLUMN `$columnName` VARCHAR(255)";
    if ($conn->query($changeDataTypeSql) !== TRUE) {
        echo "Error changing data type of column '$columnName' to VARCHAR(255): " . $conn->error;
    }
    echo "Data type of column '$columnName' changed to VARCHAR(255) in table '$tableName'.<br>";
        $renameColumnSql = "ALTER TABLE `$tableName` CHANGE COLUMN `$columnName` `item` VARCHAR(255)";
        if ($conn->query($renameColumnSql) !== TRUE) {
            echo "Error renaming column '$columnName' to 'item': " . $conn->error;
        }
        echo "Column '$columnName' renamed to 'item' in table '$tableName'.<br>";

        // Add UNIQUE constraint to the 'item' column
        $setUniqueSql = "ALTER TABLE `$tableName` ADD UNIQUE (`item`)";
        if ($conn->query($setUniqueSql) !== TRUE) {
            echo "Error setting column 'item' as unique: " . $conn->error;
        }
        echo "Column 'item' set as unique in table '$tableName'.<br>";

        $isFirstColumn = false; // Update flag
    }
}


$uniqueColumnNames[0] = 'item';

        // Import worksheet data into the table only if the table is empty
        $checkDataSql = "SELECT COUNT(*) as count FROM `$tableName`";
        $result = $conn->query($checkDataSql);
        $row = $result->fetch_assoc();

        $firstIteration = true; // Flag variable to track the first iteration

        if ($row['count'] == 0) {
            foreach ($worksheet->getRowIterator() as $row) {
                // Skip the first iteration
                if ($firstIteration) {
                    $firstIteration = false;
                    continue;
                }
        
                $rowData = [];
                foreach ($row->getCellIterator() as $cell) {
                    // Get the cell value
                    $value = $cell->getValue();
            
    // Check if the value is not null
    if ($value !== null) {
        // Escape single quotes in the value
        $value = mysqli_real_escape_string($conn, $value);
        // Add the escaped value to the row data
        $rowData[] = "'" . $value . "'";
    } else {
        // If the value is null, add NULL to the row data
        $rowData[] = "NULL";
    }
                }

                // Insert data into the table
                $sql = "INSERT INTO `$tableName` (`" . implode("`, `", $uniqueColumnNames) . "`) VALUES (" . implode(", ", $rowData) . ")";
        
                echo "Data inserted into table '$tableName'.<br>";
                // Execute SQL statement
                if ($conn->query($sql) !== TRUE) {
                    echo "Error inserting data: " . $conn->error;

                }
            }
        } else {
            echo "Data Already Present";
        }
        
    }
}
?>
</body>
</html>

<?php
function createTableFromJoinQuery($conn, $tableName = "join_query") {
    $sql = "
    SELECT *
    FROM master_list
    LEFT JOIN new_partInfo ON master_list.item = new_partInfo.item
    LEFT JOIN brands ON master_list.item = brands.item
    LEFT JOIN upc_by_item327665301234 ON master_list.item = upc_by_item327665301234.item
    LEFT JOIN item_attributes327446970405 ON master_list.item = item_attributes327446970405.item
    LEFT JOIN item_attributes_no_tooling32722 ON master_list.item = item_attributes_no_tooling32722.item
    LEFT JOIN gtin_by_item327545079472 ON master_list.item = gtin_by_item327545079472.item
    LEFT JOIN product_groups ON master_list.item = product_groups.item
    ";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        echo "Error: " . mysqli_error($conn);
        return;
    }

    // Get field names and ensure uniqueness
    $uniqueKeys = [];
    while ($field = mysqli_fetch_field($result)) {
        // Convert field name to lowercase and remove spaces
        $key = strtolower(str_replace(' ', '_', $field->name));
        // Ensure uniqueness of keys
        if (!in_array($key, $uniqueKeys)) {
            $uniqueKeys[] = $key;
        }
    }

    // Generate SQL for creating table with unique field names as columns
    $sql = "CREATE TABLE IF NOT EXISTS $tableName (
        table_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ";    
    foreach ($uniqueKeys as $key) {
        $sql .= "`$key` TEXT, "; // Enclose column names in backticks to avoid SQL syntax errors
    }
    // Remove the trailing comma and space
    $sql = rtrim($sql, ", ") . ")";
    
    if ($conn->query($sql) === TRUE) {
        echo "Table created successfully";
    } else {
        echo "Error creating table: " . $conn->error;
    }

    $rowData = [];
    while ($data = mysqli_fetch_assoc($result)) {
        $lowerCaseData = array_change_key_case($data, CASE_LOWER);
        $underscoreData = [];
        foreach ($lowerCaseData as $key => $value) {
            $newKey = str_replace(' ', '_', $key);
            $underscoreData[$newKey] = $value;
        }
        $rowData[] = $underscoreData;
    }
    
 
    if (!empty($rowData)) {
      foreach($rowData as $data){
        $keys = implode(", ", array_map(function($key) {
            return "`$key`";
        }, array_keys($data)));
        $values = implode(", ", array_map(function($value) {
            $value = str_replace("'" , "_" , $value);
            return "'" . $value . "'";
        }, $data));


        //  echo '<pre>';
        //  var_dump($keys);
        //   echo '</pre>';

        //  echo '<pre>';
        // var_dump($values);
        //   echo '</pre>';

        $sql = "INSERT INTO $tableName ($keys) VALUES ($values)";
        if($conn->query($sql) === TRUE){
            echo "Data Inserted";
        } else {
            echo "Error Inserting Data: " . $conn->error;
        }
      }
        
    } else {
        echo "No data to insert.";
    }

}




// csv function definition
function csv($conn){
    $sql = "SELECT * FROM join_query";
    $result = $conn->query($sql); 
    $filename = "export.csv";
    $filepath =  $filename; 
    $fp = fopen($filepath, 'w');

    // Get column names from the MySQL table
    $columns = [];
    $row = $result->fetch_assoc();
    foreach ($row as $key => $value) {
        $columns[] = $key;
    }

    // Write CSV headers
    fputcsv($fp, $columns);

    // Reset pointer to beginning of result set
    mysqli_data_seek($result, 0);

    // Write MySQL query results to CSV file
    while ($row = $result->fetch_assoc()) {
        fputcsv($fp, $row);
    }

    // Close file
    fclose($fp);

    // Output message
    echo "CSV file generated: <a href='$filepath' download='$filename'>$filename</a>";
}

?>




