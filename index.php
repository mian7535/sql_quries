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
    <button type="submit" name="retrieve_data">Retrieve Data</button>
    <button type='submit' name='add_fields_to_db'>Add Fields to Database</button>
    <button type="submit" name="refresh">Refresh</button>
    <button type="submit" name="join_query">Join Query</button>
    <button type="submit" name="csv">Get CSV</button>
    <button type="submit" name="delete_table">Delete Table</button>

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
    }else if (isset($_POST['delete_table']) && isset($_POST['selectedSheet'])) {
        $selectedSheet = $_POST['selectedSheet'];
        $tableName = str_replace(' ', '_', strtolower($selectedSheet)); 

  $sql = "DROP TABLE IF EXISTS $tableName";

// Execute the query
if ($conn->query($sql) === TRUE) {
    echo "Table $tableName deleted successfully";
} else {
    echo "Error deleting table: " . $conn->error;
}
    }else if (isset($_POST['join_query'])) {

        createTableFromJoinQuery($conn);

    }else if (isset($_POST['selectedSheet']) && isset($_POST['retrieve_data'])) {

        $selectedSheet = $_POST['selectedSheet'];
        echo "<h3>Sheet Data for $selectedSheet</h3>";
        
        $reader->setLoadSheetsOnly([$selectedSheet]); 
        $spreadsheet = $reader->load($excelFilePath);
        $worksheet = $spreadsheet->getSheetByName($selectedSheet);

        echo "<form method='post' action=''>";
        echo "<input type='hidden' name='dbselectedSheet' value='$selectedSheet' />";
        echo "<button type='submit' name='add_fields_to_db'>Add Fields to Database</button>";
        echo "</form>";

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

    if (isset($_POST['add_fields_to_db']) && isset($_POST['selectedSheet'])) {

        $selectedSheet = $_POST['selectedSheet'];
        $reader->setLoadSheetsOnly([$selectedSheet]); 
        $spreadsheet = $reader->load($excelFilePath);
        $worksheet = $spreadsheet->getSheetByName($selectedSheet);

        $tableName = str_replace(' ', '_', strtolower($selectedSheet)); 

        // $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
        //         `id` INT AUTO_INCREMENT PRIMARY KEY
        // )";
            
        // if ($conn->query($sql) !== TRUE) {
        //         echo "Error creating table: " . $conn->error;
        // }
        //     echo "Table '$tableName' created successfully.<br>";

        $columnNames = [];
        $isFirstRow = true;
        foreach ($worksheet->getRowIterator() as $row) {
            if ($isFirstRow) {
                foreach ($row->getCellIterator() as $cell) {
                    $columnName = str_replace(" " , "_" , strtolower(trim($cell->getValue())));
                        $columnNames[] = $columnName; 
                }
                $isFirstRow = false;
                continue;
            }
            break;
        }

        $columnNames[0] = 'item';

        $queries = [];

        foreach($columnNames as $columnName){
            // $query = "ALTER TABLE `$tableName` ADD (`$columnName` TEXT)";
            // $queries[] = $query;

            // $queries[] = "ADD COLUMN $columnName TEXT";
            $queries[] = $columnName;
        }  
        $bulkQuery = implode(',', $queries);


        echo "<pre>";
        echo $bulkQuery;
        echo "</pre>";

        // if($conn->multi_query($bulkQuery) === TRUE) {

        //     while ($conn->more_results()) {
        //         $conn->next_result();
        //     }

        //     echo "Columns Added Successfully";
        // } else {
        //     echo "Error Inserting Columns: " . $conn->error;
        // }

        // $checkDataSql = "SELECT COUNT(*) as count FROM `$tableName`";
        // $result = $conn->query($checkDataSql);
        // $row = $result->fetch_assoc();

        // $firstIteration = true; 

        // $rowKeysData = [
        //     'keys' => [],
        //     'data' => []
        // ];
        
        // if ($row['count'] == 0) {
        //     $firstIteration = true;
        //     foreach ($worksheet->getRowIterator() as $row) {
        //         $rowDataValue = [];
        //         foreach ($row->getCellIterator() as $cell) {
        //             if ($firstIteration) {
        //                 $value = str_replace(" ", "_", strtolower(trim($cell->getValue())));
        //                     $rowKeysData['keys'][] = "`" . $value . "`";
        //             } else {
        //                 $value = $cell->getValue();
        //                 if ($value !== null) {
        //                     $value = mysqli_real_escape_string($conn, $value);
        //                     $rowDataValue[]  = "'" . $value . "'";
        //                 } else {
        //                     $rowDataValue[]  = "NULL";
        //                 };
        //             }
        //         }
        //         if(!$firstIteration){
        //             $rowKeysData['data'][] =  "(" . implode("," , $rowDataValue) . ")";
        //         }
        //         $firstIteration = false;
        //     }
        // } else {
        //     echo "Data Already Present";
        // }

        // $rowKeysData['keys'][0] = '`item`';

        // $strRowData = implode(", " , $rowKeysData['data']);
        // $strRowKeys = implode(", " , $rowKeysData['keys']);

        // // echo "<pre>";
        // // echo $strRowData;
        // // echo "</pre>";

            //  $sql = "INSERT INTO $tableName ($strRowKeys) VALUES $strRowData";

        
            //          if ($conn->query($sql) === TRUE) {
            //             echo "Data inserted into table '$tableName'.<br>";   
            //          }else{
            //             echo "Error inserting data: " . $conn->error;
            //          }
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
        $sql .= "`$key` TEXT, ";
    }
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




