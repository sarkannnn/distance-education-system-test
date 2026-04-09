<?php

require_once 'localQAService.php';
require_once 'apiService.php';

/**
 * FallbackManager - Orchestrates the 4-layer chatbot fallback system.
 */
class FallbackManager
{
    private $localQA;
    private $apiService;
    private $providers;

    public function __construct($geminiKey, $openaiKey, $systemInstruction)
    {
        $this->localQA = new LocalQAService();
        $this->apiService = new ApiService($geminiKey, $openaiKey, $systemInstruction);
        
        // Define AI providers and their priority
        $this->providers = [
            ['type' => 'gemini', 'model' => 'gemini-2.0-flash'],
            ['type' => 'gemini', 'model' => 'gemini-1.5-flash-latest'],
            ['type' => 'openai', 'model' => 'gpt-4o-mini']
        ];
    }

    /**
     * Process message through the fallback layers
     */
    public function process($message, $history = [])
    {
        // --- LAYER 1: Local FAQ Match (Priority 1) ---
        $localMatch = $this->localQA->findMatch($message);
        if ($localMatch) {
            return $localMatch;
        }

        // --- LAYER 2 & 3: AI Providers (Priority 2 & 3) ---
        foreach ($this->providers as $p) {
            $reply = false;
            
            if ($p['type'] === 'gemini') {
                $reply = $this->apiService->callGemini($p['model'], $message, $history);
            } else if ($p['type'] === 'openai') {
                $reply = $this->apiService->callOpenAI($p['model'], $message, $history);
            }

            if ($reply) {
                return [
                    'reply' => $reply,
                    'source' => $p['type'],
                    'model' => $p['model']
                ];
            }
        }

        // --- LAYER 4: Hardcoded Knowledge Base (Final Fallback) ---
        return [
            'reply' => $this->getHardcodedFallback($message),
            'source' => 'fallback'
        ];
    }

    /**
     * Ultimate fallback response when everything else fails
     */
    private function getHardcodedFallback($message)
    {
        $msg = mb_strtolower($message, 'UTF-8');
        
        if (strpos($msg, 'salam') !== false || strpos($msg, 'hi') !== false) {
            return "Salam! 👋 Hazırda süni intellekt xidmətləri məşğuldur, amma mən sizə lokal bazadan kömək edə bilərəm. Naxçıvan Dövlət Universiteti Distant Təhsil sistemi haqqında nəyi bilmək istərdiniz?";
        }

        if (strpos($msg, 'dərs') !== false || strpos($msg, 'canlı') !== false) {
            return "<b>Canlı dərslər haqqında:</b> Tələbə panelində 'Canlı Dərslər' bölməsinə daxil olaraq dərslərə qoşula bilərsiniz. Əgər problem yaranarsa, internet bağlantınızı yoxlayın.";
        }

        if (strpos($msg, 'giriş') !== false || strpos($msg, 'login') !== false || strpos($msg, 'şifrə') !== false) {
            return "<b>Giriş və Şifrə:</b> Sistem TMİS (SSO) üzərindən işləyir. Şifrənizi unutmusunuzsa, TMİS portalından (tmis.ndu.edu.az) bərpa edə bilərsiniz.";
        }

        return "Sualınızı tam analiz edə bilmədim. 😔 Hazırda sistemlərimizdə texniki məhdudiyyət var. Zəhmət olmasa bir qədər sonra yenidən cəhd edin və ya <b>distant@ndu.edu.az</b> ünvanına yazın.";
    }
}
