<?php
/**
 * Simple HTML to PDF converter
 * This is a basic implementation that converts HTML to PDF format
 * For production use, consider using a full-featured library like TCPDF or DomPDF
 */

class HTML2PDF {
    private $htmlContent;
    private $title;
    
    public function __construct($htmlContent, $title = "Report") {
        $this->htmlContent = $htmlContent;
        $this->title = $title;
    }
    
    public function output($filename = 'report.pdf') {
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Simple PDF structure
        echo "%PDF-1.4\n";
        
        // For now, we'll just output the HTML with a note that this would be a PDF
        // In a real implementation, this would generate actual PDF content
        echo "This is a placeholder for a PDF report.\n\n";
        echo "In a production environment, this would generate a proper PDF using a library like TCPDF or DomPDF.\n\n";
        echo "Report Title: " . $this->title . "\n";
        echo "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        echo "Content:\n";
        echo strip_tags($this->htmlContent);
        
        // Exit to prevent additional output
        exit();
    }
    
    // Method to generate a more structured text report
    public function generateTextReport() {
        $report = "INVENTORY MANAGEMENT SYSTEM REPORT\n";
        $report .= str_repeat("=", 50) . "\n";
        $report .= "Report Title: " . $this->title . "\n";
        $report .= "Generated on: " . date('F j, Y g:i A') . "\n";
        $report .= str_repeat("-", 50) . "\n\n";
        
        // Extract text content from HTML
        $textContent = strip_tags($this->htmlContent);
        // Remove extra whitespace
        $textContent = preg_replace('/\s+/', ' ', $textContent);
        $textContent = str_replace('FRW ', 'FRW', $textContent);
        
        $report .= $textContent;
        $report .= "\n\n" . str_repeat("=", 50) . "\n";
        $report .= "End of Report\n";
        
        return $report;
    }
}
?>