<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\EntryRepo;
use App\Repositories\FeedbackRepo;
use App\Repositories\AuditRepo;
use App\Services\AiFeedbackService;
use App\Utils\Response;
use App\Utils\Security;

final class StudentController
{
    public function __construct(
        private EntryRepo $entries,
        private FeedbackRepo $feedback,
        private AuditRepo $audit,
        private AiFeedbackService $aiFeedback
    ) {}

    public function listEntries(): never
    {
        Security::requireLogin();
        $studentId = Security::getUserId();
        if ($studentId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-90 days'));
        $to = $_GET['to'] ?? date('Y-m-d');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            Response::error('VALIDATION_ERROR', 'Kuupäev peab olema YYYY-MM-DD', 400);
        }

        $rows = $this->entries->listForStudent((int)$studentId, (string)$from, (string)$to);
        $mapped = array_map(fn(array $r) => [
            'id' => (int)$r['id'],
            'entry_date' => $r['entry_date'],
            'weight_kg' => $r['weight_kg'] !== null ? (float)$r['weight_kg'] : null,
            'pushups' => $r['pushups'] !== null ? (int)$r['pushups'] : null,
            'note' => $r['note'],
            'feedback' => $r['feedback_text'] ? ['text' => $r['feedback_text']] : null,
        ], $rows);

        Response::json(['entries' => $mapped], 200);
    }

    public function createEntry(): never
    {
        Security::requireLogin();
        $studentId = Security::getUserId();
        if ($studentId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $data = Security::readJsonBody();
        $entryDate = trim((string)($data['entry_date'] ?? ''));
        $weightKg = isset($data['weight_kg']) ? (float)$data['weight_kg'] : null;
        $pushups = array_key_exists('pushups', $data) && $data['pushups'] !== null ? (int)$data['pushups'] : null;
        $note = isset($data['note']) ? trim((string)$data['note']) : null;

        if ($entryDate === '') $entryDate = date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
            Response::error('VALIDATION_ERROR', 'Kuupäev peab olema YYYY-MM-DD', 400);
        }

        if ($weightKg === 0.0) $weightKg = null;
        if ($pushups === null && $weightKg === null && ($note === null || $note === '')) {
            Response::error('VALIDATION_ERROR', 'Vähemalt üks mõõdik (kaal, kätekõverdused või märkus) peab olema täidetud', 400);
        }

        if ($pushups !== null && ($pushups < 0 || $pushups > 300)) {
            Response::error('VALIDATION_ERROR', 'Kätekõverdused peavad olema 0–300', 400);
        }
        if ($weightKg !== null && ($weightKg < 20 || $weightKg > 300)) {
            Response::error('VALIDATION_ERROR', 'Kaal peab olema 20–300 kg', 400);
        }
        if ($note !== null && (function_exists('mb_strlen') ? mb_strlen($note) : strlen($note)) > 300) {
            Response::error('VALIDATION_ERROR', 'Märkus max 300 tähemärki', 400);
        }

        if ($this->entries->existsForDate((int)$studentId, $entryDate)) {
            Response::error('DUPLICATE_ENTRY_DATE', 'Selle kuupäeva sissekanne on juba olemas.', 409);
        }

        $entryId = $this->entries->create((int)$studentId, $entryDate, $weightKg, $pushups, $note);
        $this->audit->log((int)$studentId, 'ENTRY_CREATE', 'entry', $entryId);

        $feedbackText = $this->generateFeedbackForEntry((int)$studentId, $entryId, $entryDate, $pushups, $weightKg);
        $this->feedback->create($entryId, 'openai', 'gpt-4o-mini', $feedbackText);

        $entry = [
            'id' => $entryId,
            'entry_date' => $entryDate,
            'weight_kg' => $weightKg,
            'pushups' => $pushups,
            'note' => $note,
        ];

        Response::json([
            'entry' => $entry,
            'feedback' => ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'text' => $feedbackText],
        ], 201);
    }

    public function updateEntry(int $id): never
    {
        Security::requireLogin();
        $studentId = Security::getUserId();
        if ($studentId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $entry = $this->entries->findById($id);
        if (!$entry || (int)$entry['student_user_id'] !== $studentId) {
            Response::error('NOT_FOUND', 'Sissekannet ei leitud', 404);
        }

        $data = Security::readJsonBody();
        $weightKg = array_key_exists('weight_kg', $data) ? ($data['weight_kg'] === null ? null : (float)$data['weight_kg']) : ($entry['weight_kg'] !== null ? (float)$entry['weight_kg'] : null);
        $pushups = array_key_exists('pushups', $data) ? ($data['pushups'] === null ? null : (int)$data['pushups']) : ($entry['pushups'] !== null ? (int)$entry['pushups'] : null);
        $note = array_key_exists('note', $data) ? ($data['note'] === null ? null : trim((string)$data['note'])) : $entry['note'];
        $entryDate = (string)$entry['entry_date'];

        if ($weightKg === 0.0) $weightKg = null;
        if ($pushups === null && $weightKg === null && ($note === null || $note === '')) {
            Response::error('VALIDATION_ERROR', 'Vähemalt üks mõõdik peab olema täidetud', 400);
        }
        if ($pushups !== null && ($pushups < 0 || $pushups > 300)) {
            Response::error('VALIDATION_ERROR', 'Kätekõverdused peavad olema 0–300', 400);
        }
        if ($weightKg !== null && ($weightKg < 20 || $weightKg > 300)) {
            Response::error('VALIDATION_ERROR', 'Kaal peab olema 20–300 kg', 400);
        }
        if ($note !== null && (function_exists('mb_strlen') ? mb_strlen($note) : strlen($note)) > 300) {
            Response::error('VALIDATION_ERROR', 'Märkus max 300 tähemärki', 400);
        }

        $this->entries->update($id, $weightKg, $pushups, $note);
        $this->audit->log((int)$studentId, 'ENTRY_UPDATE', 'entry', $id);

        $feedbackText = $this->generateFeedbackForEntry((int)$studentId, $id, $entryDate, $pushups, $weightKg);
        $this->feedback->upsert($id, 'openai', 'gpt-4o-mini', $feedbackText);

        $updated = $this->entries->findById($id);
        Response::json([
            'ok' => true,
            'entry' => [
                'id' => (int)$updated['id'],
                'entry_date' => $updated['entry_date'],
                'weight_kg' => $updated['weight_kg'] !== null ? (float)$updated['weight_kg'] : null,
                'pushups' => $updated['pushups'] !== null ? (int)$updated['pushups'] : null,
                'note' => $updated['note'],
            ],
            'feedback' => ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'text' => $feedbackText],
        ], 200);
    }

    public function deleteEntry(int $id): never
    {
        Security::requireLogin();
        $studentId = Security::getUserId();
        if ($studentId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $entry = $this->entries->findById($id);
        if (!$entry || (int)$entry['student_user_id'] !== $studentId) {
            Response::error('NOT_FOUND', 'Sissekannet ei leitud', 404);
        }

        $this->entries->delete($id);
        $this->audit->log((int)$studentId, 'ENTRY_DELETE', 'entry', $id);

        Response::json(['ok' => true], 200);
    }

    public function getFeedback(int $id): never
    {
        Security::requireLogin();
        $studentId = Security::getUserId();
        if ($studentId === null) Response::error('UNAUTHORIZED', 'Palun logi sisse', 401);

        $entry = $this->entries->findById($id);
        if (!$entry || (int)$entry['student_user_id'] !== $studentId) {
            Response::error('NOT_FOUND', 'Sissekannet ei leitud', 404);
        }

        $fb = $this->feedback->getForEntry($id);
        if (!$fb) {
            Response::json(['entry_id' => $id, 'feedback' => null], 200);
        }

        Response::json([
            'entry_id' => $id,
            'feedback' => ['text' => $fb['feedback_text']],
        ], 200);
    }

    private function generateFeedbackForEntry(int $studentId, int $entryId, string $entryDate, ?int $pushups, ?float $weightKg): string
    {
        $last90 = $this->entries->listForStudent($studentId, date('Y-m-d', strtotime('-90 days')), date('Y-m-d'));
        $summary = $this->buildSummary($last90);
        $prevPushups = $this->getPrevPushupsExcludingEntry($last90, $entryDate, $entryId);
        $delta = $pushups !== null && $prevPushups !== null ? $pushups - $prevPushups : 0;
        $consistency14d = $this->countEntriesLast14Days($studentId, $entryDate);

        return $this->aiFeedback->generate(
            $summary,
            $pushups ?? 0,
            $weightKg,
            $delta,
            $consistency14d
        );
    }

    private function buildSummary(array $rows): string
    {
        $parts = [];
        foreach (array_slice($rows, 0, 5) as $r) {
            $d = $r['entry_date'];
            $p = $r['pushups'] ?? '–';
            $w = $r['weight_kg'] !== null ? 'kaal sisestatud' : '–';
            $parts[] = "{$d}: pushups={$p}, {$w}";
        }
        return implode('; ', $parts) ?: 'puudub';
    }

    private function getPrevPushupsExcludingEntry(array $rows, string $today, int $excludeId): ?int
    {
        foreach ($rows as $r) {
            if ((int)$r['id'] === $excludeId) {
                continue;
            }
            if ($r['entry_date'] <= $today && $r['pushups'] !== null) {
                return (int)$r['pushups'];
            }
        }
        return null;
    }

    private function countEntriesLast14Days(int $studentId, string $untilDate): int
    {
        $from = date('Y-m-d', strtotime($untilDate . ' -14 days'));
        $rows = $this->entries->listForStudent($studentId, $from, $untilDate);
        return count($rows);
    }
}
