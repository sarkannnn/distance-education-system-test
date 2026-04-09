<?php

/**
 * LocalQAService - Handles local FAQ matching and fuzzy search.
 * Layer 1 (Priority 1) of the Chatbot Fallback System.
 */
class LocalQAService
{
    private $faqs = [];
    private $db;
    private $jsonPath;

    public function __construct()
    {
        $this->jsonPath = dirname(__DIR__) . '/data/local_qa_data.json';
        $this->loadFaqs();
    }

    /**
     * Load FAQs from JSON file and Database
     */
    private function loadFaqs()
    {
        // 1. Load from JSON
        if (file_exists($this->jsonPath)) {
            $jsonData = json_encode(json_decode(file_get_contents($this->jsonPath), true)); // Normalize
            $data = json_decode($jsonData, true);
            if (isset($data['faqs'])) {
                $this->faqs = array_merge($this->faqs, $data['faqs']);
            }
        }

        // 2. Load from Database
        try {
            require_once dirname(dirname(__DIR__)) . '/student/config/database.php';
            $this->db = Database::getInstance();
            $dbFaqs = $this->db->fetchAll(
                "SELECT id, question, answer, category, keywords FROM faqs WHERE is_active = 1"
            );
            
            foreach ($dbFaqs as $f) {
                // Ensure keywords is an array
                if (is_string($f['keywords'])) {
                    $f['keywords'] = array_map('trim', explode(',', $f['keywords']));
                }
                $this->faqs[] = $f;
            }
        } catch (Exception $e) {
            error_log("LocalQAService DB Load Error: " . $e->getMessage());
        }
    }

    /**
     * Find best matching FAQ for a user message
     */
    public function findMatch($userMessage)
    {
        if (empty($userMessage)) return null;

        $bestMatch = null;
        $highestScore = 0;
        $threshold = 35; // Confidence threshold (%) - Lowered from 40 for better reach

        $normalizedMsg = $this->normalizeText($userMessage);
        $userWords = $this->extractWords($normalizedMsg);

        foreach ($this->faqs as $faq) {
            $score = $this->calculateScore($normalizedMsg, $userWords, $faq);
            
            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $faq;
            }
        }

        if ($highestScore >= $threshold) {
            // Log successful match to stats
            $this->logClick($bestMatch['question']);
            
            return [
                'reply' => $bestMatch['answer'],
                'score' => $highestScore,
                'category' => $bestMatch['category'],
                'source' => 'local'
            ];
        }

        return null;
    }

    /**
     * Calculate similarity score (0-100)
     */
    private function calculateScore($msg, $msgWords, $faq)
    {
        $score = 0;
        $faqQuestion = $this->normalizeText($faq['question']);
        $faqKeywords = isset($faq['keywords']) && is_array($faq['keywords']) ? $faq['keywords'] : [];
        
        // 1. Exact or Substring match (High weight)
        if ($msg === $faqQuestion) {
            return 100;
        }
        if (mb_strpos($faqQuestion, $msg) !== false || mb_strpos($msg, $faqQuestion) !== false) {
            $score += 40;
        }

        // 2. Keyword Overlap (Medium weight)
        $matchCount = 0;
        foreach ($faqKeywords as $kw) {
            $kwNorm = $this->normalizeText($kw);
            if (mb_strpos($msg, $kwNorm) !== false) {
                $matchCount++;
            }
        }
        
        if (count($faqKeywords) > 0) {
            $kwScore = ($matchCount / count($faqKeywords)) * 50;
            $score += $kwScore;
        }

        // 3. Word Matching
        $faqWords = $this->extractWords($faqQuestion);
        $intersect = array_intersect($msgWords, $faqWords);
        if (count($faqWords) > 0) {
            $wordScore = (count($intersect) / count($faqWords)) * 30;
            $score += $wordScore;
        }

        // 4. Levenshtein Distance for short messages (Low weight)
        if (mb_strlen($msg) < 50) {
            $lev = levenshtein($msg, $faqQuestion);
            $maxLen = max(mb_strlen($msg), mb_strlen($faqQuestion));
            if ($maxLen > 0) {
                $levScore = (1 - ($lev / $maxLen)) * 20;
                $score += $levScore;
            }
        }

        return min(100, $score);
    }

    private function normalizeText($text)
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text); // Remove punctuation
        return trim($text);
    }

    private function extractWords($text)
    {
        return preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    }

    private function logClick($question)
    {
        if (!$this->db) return;
        try {
            $sql = "INSERT INTO faq_click_stats (faq_question, click_count) 
                    VALUES (?, 1) 
                    ON DUPLICATE KEY UPDATE click_count = click_count + 1, last_clicked_at = CURRENT_TIMESTAMP";
            $this->db->query($sql, [$question]);
        } catch (Exception $e) {
            // Ignore logging errors
        }
    }
}
