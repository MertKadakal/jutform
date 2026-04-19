<?php

namespace JutForm\Controllers;

use JutForm\Core\Request;
use JutForm\Core\Response;

class FeatureController
{
    public function exportPdf(Request $request, string $id): void
    {
        $uid = \JutForm\Core\RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        
        $form = \JutForm\Models\Form::find((int) $id);
        if (!$form || (int) $form['user_id'] !== $uid) {
            Response::error('Not found', 404);
        }
        
        // Fetch submissions (limit to 1000 for PDF stability)
        $submissions = \JutForm\Models\Submission::findByForm((int) $id, 1000, 0);
        
        $templatePath = dirname(__DIR__, 2) . '/resources/pdf-template.html';
        if (!is_readable($templatePath)) {
            Response::error('Template not found', 500);
        }
        $template = file_get_contents($templatePath);
        
        $rowsHtml = '';
        foreach ($submissions as $idx => $s) {
            $data = json_decode($s['data_json'], true) ?: [];
            $dataSummary = '';
            foreach ($data as $key => $val) {
                $dataSummary .= "<b>" . htmlspecialchars($key) . "</b>: " . htmlspecialchars((string)$val) . "<br>";
            }
            
            $rowsHtml .= "<tr>";
            $rowsHtml .= "  <td style='vertical-align: top;'>" . ($idx + 1) . "</td>";
            $rowsHtml .= "  <td>$dataSummary</td>";
            $rowsHtml .= "</tr>";
        }
        
        $html = str_replace(
            ['{{form_name}}', '{{generated_at}}', '{{submission_count}}', '{{rows}}'],
            [
                htmlspecialchars($form['title']), 
                date('Y-m-d H:i:s'), 
                count($submissions), 
                $rowsHtml
            ],
            $template
        );
        
        // Initialize DOMPDF
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans'); // Supports more characters
        
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = 'form-' . $id . '-submissions-' . date('Ymd') . '.pdf';
        
        Response::raw($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function createPayment(Request $request): void
    {
        Response::error('Not implemented', 501);
    }

    public function analyticsSummary(Request $request): void
    {
        Response::error('Not implemented', 501);
    }
}
