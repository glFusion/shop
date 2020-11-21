<?php
/**
 * PDF trait to convert HTML to PDF output.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.2.3
 * @since       v1.2.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Traits;


/**
 * Utility trait to create PDF output from HTML.
 * @package shop
 */
trait PDF
{
    protected $html2pdf = NULL;

    public function initPDF()
    {
        USES_lglib_class_html2pdf();
        try {
            if (class_exists('\\Spipu\\Html2Pdf\\Html2Pdf')) {
                $this->html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'en');
            } else {
                $this->html2pdf = new \HTML2PDF('P', 'A4', 'en');
            }
            //$html2pdf->setModeDebug();
            $this->html2pdf->setDefaultFont('Arial');
        } catch(HTML2PDF_exception $e) {
            SHOP_log($e);
            return false;
        }

        return true;
    }

    public function writePDF($content)
    {
        try {
            $this->html2pdf->writeHTML($content);
        } catch(HTML2PDF_exception $e) {
            SHOP_log($e);
            return false;
        }
    }


    public function finishPDF($file='output')
    {
        $this->html2pdf->output($file . '.pdf', 'I');
    }

}

?>
