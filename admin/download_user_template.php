<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

require_admin();

if (!is_super_admin()) {
    die("Unauthorized access. Super admin privilege required.");
}

$filename = "ziyafat_student_bulk_import_template.xls";

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <Styles>
  <Style ss:ID="Header">
   <Font ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#000066" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center"/>
  </Style>
  <Style ss:ID="StringCell">
   <NumberFormat ss:Format="@"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="Students">
  <Table>
   <Column ss:Width="110"/>
   <Column ss:Width="110"/>
   <Column ss:Width="240"/>
   <Column ss:Width="220"/>
   <Column ss:Width="140"/>
   <Row ss:StyleID="Header">
    <Cell><Data ss:Type="String">TR Number</Data></Cell>
    <Cell><Data ss:Type="String">ITS Number</Data></Cell>
    <Cell><Data ss:Type="String">Full Name</Data></Cell>
    <Cell><Data ss:Type="String">Email</Data></Cell>
    <Cell><Data ss:Type="String">Phone Number</Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="StringCell"><Data ss:Type="String">26001</Data></Cell>
    <Cell ss:StyleID="StringCell"><Data ss:Type="String">30450001</Data></Cell>
    <Cell><Data ss:Type="String">Murtaza Bhai Shabbir Bhai</Data></Cell>
    <Cell><Data ss:Type="String">26001@jameasaifiyah.edu</Data></Cell>
    <Cell ss:StyleID="StringCell"><Data ss:Type="String">+919876543210</Data></Cell>
   </Row>
   <Row>
    <Cell ss:StyleID="StringCell"><Data ss:Type="String">26002</Data></Cell>
    <Cell ss:StyleID="StringCell"><Data ss:Type="String">30450002</Data></Cell>
    <Cell><Data ss:Type="String">Fatema Bai Aliasgar Bhai</Data></Cell>
    <Cell><Data ss:Type="String">26002@jameasaifiyah.edu</Data></Cell>
    <Cell ss:StyleID="StringCell"><Data ss:Type="String">+919876543211</Data></Cell>
   </Row>
  </Table>
 </Worksheet>
</Workbook>
<?php exit(); ?>
