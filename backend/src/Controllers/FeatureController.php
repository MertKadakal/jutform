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
        $uid = \JutForm\Core\RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }

        $body = $request->jsonBody();
        $amount = (float) ($body['amount'] ?? 0);
        if ($amount <= 0) {
            Response::error('Invalid amount', 400);
        }

        $pdo = \JutForm\Core\Database::getInstance();
        
        // 0. Get API Key from app_config
        $stmt = $pdo->prepare('SELECT value FROM app_config WHERE config_key = ? LIMIT 1');
        $stmt->execute(['payment_api_key']);
        $apiKey = $stmt->fetchColumn() ?: '';

        // 1. Fetch Salt
        $saltRes = $this->gatewayRequest('GET', '/salt', $apiKey);
        if (!$saltRes || !isset($saltRes['salt'])) {
            Response::error('Gateway unreachable or returned invalid salt', 503);
        }
        $salt = (string) $saltRes['salt'];

        // 2. Compute Hash
        $now = gmdate('Y-m-d H:i:s');
        $hashInput = $uid . '|' . number_format($amount, 2, '.', '') . '|' . $now;
        $hash = hash('sha256', $hashInput . $salt);

        // 3. Charge
        $chargeRes = $this->gatewayRequest('POST', '/charge', $apiKey, [
            'hash' => $hash,
            'user_id' => (int) $uid,
            'amount' => (float) $amount,
            'datetime' => $now
        ]);

        if (!$chargeRes || !isset($chargeRes['status'])) {
            Response::error('Gateway error during charge', 503);
        }

        // 4. Persist result
        $status = $chargeRes['status'] === 'approved' ? 'approved' : 'declined';
        $txnId = $chargeRes['transaction_id'] ?? null;

        $stmt = $pdo->prepare(
            'INSERT INTO payments (user_id, amount, transaction_id, status, gateway_hash, paid_at) 
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$uid, $amount, $txnId, $status, $hash, $now]);

        if ($status === 'approved') {
            Response::json(['transaction_id' => $txnId, 'status' => 'approved']);
        } else {
            Response::raw(json_encode([
                'status' => 'declined', 
                'reason' => $chargeRes['reason'] ?? 'Unknown'
            ]), 402, ['Content-Type' => 'application/json']);
        }
    }

    private function gatewayRequest(string $method, string $path, string $apiKey, ?array $data = null): ?array
    {
        $url = 'http://payment-gateway' . $path;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($res === false) return null;
        return json_decode($res, true);
    }

    public function analyticsSummary(Request $request): void
    {
        Response::error('Not implemented', 501);
    }
}
