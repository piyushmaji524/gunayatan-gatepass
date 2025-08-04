<?php
/**
 * Translation Helper for Security Panel
 * Uses free Google Translate API to translate item names to Hindi
 */

/**
 * Get local dictionary for common items (most accurate)
 * @return array Associative array of English => Hindi translations
 */
function getLocalDictionary() {
    return [
        // Office Items
        'computer' => 'कंप्यूटर',
        'laptop' => 'लैपटॉप',
        'mobile phone' => 'मोबाइल फोन',
        'printer' => 'प्रिंटर',
        'scanner' => 'स्कैनर',
        'monitor' => 'मॉनिटर',
        'keyboard' => 'कीबोर्ड',
        'mouse' => 'माउस',
        'office chair' => 'कार्यालय कुर्सी',
        'desk' => 'डेस्क',
        'table' => 'टेबल',
        'chair' => 'कुर्सी',
        
        // Documents & Stationery
        'books' => 'पुस्तकें',
        'files' => 'फाइलें',
        'documents' => 'दस्तावेज',
        'papers' => 'कागजात',
        'notebook' => 'नोटबुक',
        'pen' => 'पेन',
        'pencil' => 'पेंसिल',
        'folder' => 'फोल्डर',
        
        // Tools & Equipment
        'tools' => 'उपकरण',
        'equipment' => 'सामान',
        'machine' => 'मशीन',
        'device' => 'यंत्र',
        'cable' => 'केबल',
        'wire' => 'तार',
        'charger' => 'चार्जर',
        'adapter' => 'एडेप्टर',
        
        // Common Items
        'bag' => 'बैग',
        'box' => 'बॉक्स',
        'package' => 'पैकेज',
        'container' => 'कंटेनर',
        'bottle' => 'बोतल',
        'key' => 'चाबी',
        'card' => 'कार्ड',
        'id card' => 'पहचान पत्र',
        
        // Electronics
        'camera' => 'कैमरा',
        'tablet' => 'टैबलेट',
        'headphones' => 'हेडफोन',
        'speaker' => 'स्पीकर',
        'microphone' => 'माइक्रोफोन',
        'projector' => 'प्रोजेक्टर',
        
        // Furniture
        'furniture' => 'फर्नीचर',
        'cabinet' => 'कैबिनेट',
        'shelf' => 'शेल्फ',
        'drawer' => 'दराज',
        'cupboard' => 'अलमारी',
        
        // Vehicles & Parts
        'vehicle' => 'वाहन',
        'car' => 'कार',
        'bike' => 'बाइक',
        'scooter' => 'स्कूटर',
        'bicycle' => 'साइकिल',
        'spare parts' => 'पुर्जे',
        
        // Common Verbs/Actions
        'repair' => 'मरम्मत',
        'maintenance' => 'रखरखाव',
        'installation' => 'स्थापना',
        'testing' => 'परीक्षण',
        'inspection' => 'निरीक्षण',
        
        // Locations (Common Places)
        'office' => 'कार्यालय',
        'warehouse' => 'गोदाम',
        'factory' => 'कारखाना',
        'home' => 'घर',
        'building' => 'भवन',
        'floor' => 'मंजिल',
        'room' => 'कमरा',
        'hall' => 'हॉल',
        'basement' => 'तहखाना',
        'parking' => 'पार्किंग',
        'gate' => 'गेट',
        'entrance' => 'प्रवेश द्वार',
        'exit' => 'निकास',
        'reception' => 'स्वागत कक्ष',
        'lobby' => 'लॉबी',
        'cafeteria' => 'कैंटीन',
        'store' => 'भंडार',
        'workshop' => 'कार्यशाला',
        'laboratory' => 'प्रयोगशाला',
        'meeting room' => 'बैठक कक्ष',
        'conference room' => 'सम्मेलन कक्ष',
        'ground floor' => 'भूतल',
        'first floor' => 'पहली मंजिल',
        'second floor' => 'दूसरी मंजिल',
        'third floor' => 'तीसरी मंजिल',
        'fourth floor' => 'चौथी मंजिल',
        'main building' => 'मुख्य भवन',
        'admin block' => 'प्रशासनिक खंड',
        'production unit' => 'उत्पादन इकाई',
        'security cabin' => 'सुरक्षा केबिन',
        'canteen' => 'भोजनालय',
        'library' => 'पुस्तकालय',
        'training center' => 'प्रशिक्षण केंद्र',
        'head office' => 'मुख्यालय',
        'branch office' => 'शाखा कार्यालय',
        'guest house' => 'अतिथि गृह',
        'quarters' => 'आवास',
        'colony' => 'कॉलोनी',
        'campus' => 'परिसर',
        'compound' => 'परिसर',
        'premises' => 'परिसर',
        
        // Material Types
        'electronics' => 'इलेक्ट्रॉनिक्स',
        'furniture' => 'फर्नीचर',
        'stationery' => 'लेखन सामग्री',
        'documents' => 'दस्तावेज',
        'equipment' => 'उपकरण',
        'machinery' => 'मशीनरी',
        'tools' => 'औजार',
        'spare parts' => 'पुर्जे',
        'raw materials' => 'कच्चा माल',
        'finished goods' => 'तैयार माल',
        'consumables' => 'उपभोग्य वस्तुएं',
        'office supplies' => 'कार्यालयी आपूर्ति',
        'personal items' => 'व्यक्तिगत सामान',
        'medical equipment' => 'चिकित्सा उपकरण',
        'safety equipment' => 'सुरक्षा उपकरण',
        'cleaning supplies' => 'सफाई की सामग्री',
        'chemicals' => 'रसायन',
        'food items' => 'खाद्य पदार्थ',
        'beverages' => 'पेय पदार्थ',
        'packaging materials' => 'पैकेजिंग सामग्री',
        'promotional materials' => 'प्रचार सामग्री',
        'samples' => 'नमूने',
        'gifts' => 'उपहार',
        'uniforms' => 'वर्दी',
        'books and manuals' => 'पुस्तकें और मैनुअल',
        'software' => 'सॉफ्टवेयर',
        'hardware' => 'हार्डवेयर',
        'accessories' => 'सहायक उपकरण',
        'components' => 'घटक',
        'miscellaneous' => 'विविध',
        'others' => 'अन्य',
        
        // Purpose/Reasons
        'office use' => 'कार्यालयी उपयोग',
        'home use' => 'घरेलू उपयोग',
        'personal use' => 'व्यक्तिगत उपयोग',
        'official work' => 'आधिकारिक कार्य',
        'repair' => 'मरम्मत',
        'maintenance' => 'रखरखाव',
        'testing' => 'परीक्षण',
        'demonstration' => 'प्रदर्शन',
        'presentation' => 'प्रस्तुति',
        'meeting' => 'बैठक',
        'training' => 'प्रशिक्षण',
        'installation' => 'स्थापना',
        'replacement' => 'प्रतिस्थापन',
        'upgrade' => 'उन्नयन',
        'backup' => 'बैकअप',
        'emergency' => 'आपातकाल',
        'project work' => 'परियोजना कार्य',
        'field work' => 'क्षेत्रीय कार्य',
        'client visit' => 'ग्राहक मुलाकात',
        'vendor visit' => 'विक्रेता मुलाकात',
        'inspection' => 'निरीक्षण',
        'audit' => 'लेखा परीक्षा',
        'survey' => 'सर्वेक्षण',
        'research' => 'अनुसंधान',
        'development' => 'विकास',
        'quality check' => 'गुणवत्ता जांच',
        'delivery' => 'वितरण',
        'return' => 'वापसी',
        'exchange' => 'अदला-बदली',
        'loan' => 'ऋण',
        'temporary use' => 'अस्थायी उपयोग',
        'permanent transfer' => 'स्थायी स्थानांतरण',
        'sale' => 'बिक्री',
        'purchase' => 'खरीद',
        'rental' => 'किराया',
        'exhibition' => 'प्रदर्शनी',
        'conference' => 'सम्मेलन',
        'workshop' => 'कार्यशाला',
        'seminar' => 'संगोष्ठी'
    ];
}

/**
 * Translate text from English to Hindi using multiple methods for best accuracy
 * @param string $text The text to translate
 * @return string The translated text or original text if translation fails
 */
function translateToHindi($text) {
    // Cache translations to avoid repeated API calls
    static $translation_cache = array();
    
    // Clean and normalize text
    $text = trim($text);
    if (empty($text)) {
        return $text;
    }
    
    // Check cache first
    $cache_key = strtolower($text);
    if (isset($translation_cache[$cache_key])) {
        return $translation_cache[$cache_key];
    }
    
    // Step 1: Check local dictionary first (most accurate)
    $local_dict = getLocalDictionary();
    if (isset($local_dict[$cache_key])) {
        $translation_cache[$cache_key] = $local_dict[$cache_key];
        return $local_dict[$cache_key];
    }
    
    // Step 2: Try multiple translation APIs in order of accuracy
    $translated = tryLibreTranslate($text) ?: 
                  tryMyMemoryAPI($text) ?: 
                  tryDeepLTranslate($text) ?: 
                  tryGoogleTranslate($text) ?: 
                  tryYandexTranslate($text) ?:
                  tryMicrosoftTranslate($text) ?:
                  $text; // fallback to original
    
    // Cache the translation
    $translation_cache[$cache_key] = $translated;
    
    return $translated;
}

/**
 * Try LibreTranslate API (Open source, very accurate)
 * @param string $text The text to translate
 * @return string|false Translated text or false on failure
 */
function tryLibreTranslate($text) {
    try {
        // LibreTranslate free instance
        $url = "https://libretranslate.de/translate";
        
        $data = json_encode([
            'q' => $text,
            'source' => 'en',
            'target' => 'hi',
            'format' => 'text'
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['translatedText']) && !empty($data['translatedText'])) {
                return $data['translatedText'];
            }
        }
    } catch (Exception $e) {
        error_log("LibreTranslate error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Try MyMemory API (Very accurate, 1000 free requests/day)
 * @param string $text The text to translate
 * @return string|false Translated text or false on failure
 */
function tryMyMemoryAPI($text) {
    try {
        $url = "https://api.mymemory.translated.net/get?q=" . urlencode($text) . "&langpair=en|hi";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['responseData']['translatedText']) && 
                !empty($data['responseData']['translatedText']) &&
                strtolower($data['responseData']['translatedText']) !== strtolower($text)) {
                return $data['responseData']['translatedText'];
            }
        }
    } catch (Exception $e) {
        error_log("MyMemory API error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Try Google Translate API (fallback)
 * @param string $text The text to translate
 * @return string|false Translated text or false on failure
 */
function tryGoogleTranslate($text) {
    try {
        $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=hi&dt=t&q=" . urlencode($text);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data[0][0][0])) {
                return $data[0][0][0];
            }
        }
    } catch (Exception $e) {
        error_log("Google Translate error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Try Microsoft Translator API (more reliable than Bing scraping)
 * @param string $text The text to translate
 * @return string|false Translated text or false on failure
 */
function tryMicrosoftTranslate($text) {
    try {
        // Microsoft Translator Text API (free tier available)
        $url = "https://api.cognitive.microsofttranslator.com/translate?api-version=3.0&from=en&to=hi";
        
        $data = json_encode([
            ['Text' => $text]
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data),
            'X-ClientTraceId: ' . uniqid()
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data[0]['translations'][0]['text'])) {
                return $data[0]['translations'][0]['text'];
            }
        }
    } catch (Exception $e) {
        error_log("Microsoft Translate error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Try Yandex Translate API (very good for Indian languages)
 * @param string $text The text to translate
 * @return string|false Translated text or false on failure
 */
function tryYandexTranslate($text) {
    try {
        // Yandex Translate API (free tier available)
        $url = "https://translate.yandex.net/api/v1.5/tr.json/translate?key=trnsl.1.1.20210101T000000Z.0123456789abcdef.0123456789abcdef0123456789abcdef01234567&text=" . urlencode($text) . "&lang=en-hi";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['text'][0]) && !empty($data['text'][0])) {
                return $data['text'][0];
            }
        }
    } catch (Exception $e) {
        error_log("Yandex Translate error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Try DeepL API (known for high accuracy, has free tier)
 * @param string $text The text to translate
 * @return string|false Translated text or false on failure
 */
function tryDeepLTranslate($text) {
    try {
        // DeepL API free endpoint
        $url = "https://api-free.deepl.com/v2/translate";
        
        $data = [
            'text' => $text,
            'source_lang' => 'EN',
            'target_lang' => 'HI'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['translations'][0]['text'])) {
                return $data['translations'][0]['text'];
            }
        }
    } catch (Exception $e) {
        error_log("DeepL Translate error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Get Hindi translation with fallback
 * @param string $text The text to translate
 * @return array Array with 'english' and 'hindi' keys
 */
function getItemTranslation($text) {
    return array(
        'english' => $text,
        'hindi' => translateToHindi($text)
    );
}

/**
 * Display item name with Hindi translation and quality indicator
 * @param string $item_name The item name in English
 * @param bool $show_both Whether to show both languages or just English with hover
 * @return string HTML formatted item name
 */
function displayItemWithTranslation($item_name, $show_both = true) {
    $translation = getItemTranslation($item_name);
    $quality = getTranslationQuality($item_name, $translation['hindi']);
    
    if ($show_both && $translation['hindi'] !== $translation['english']) {
        $quality_icon = getQualityIcon($quality);
        return '<div class="item-with-translation">' .
               '<div class="english-name">' . htmlspecialchars($translation['english']) . '</div>' .
               '<div class="hindi-name text-muted">' . 
               htmlspecialchars($translation['hindi']) . 
               ' <span class="translation-quality ms-1" title="Translation Quality: ' . ucfirst($quality) . '">' . 
               $quality_icon . '</span></div>' .
               '</div>';
    } else {
        // Show English with Hindi as tooltip
        return '<span class="item-with-tooltip" data-bs-toggle="tooltip" title="Hindi: ' . 
               htmlspecialchars($translation['hindi']) . '">' . 
               htmlspecialchars($translation['english']) . '</span>';
    }
}

/**
 * Get translation quality indicator
 * @param string $original Original text
 * @param string $translated Translated text
 * @return string Quality level (excellent, good, fair, poor)
 */
function getTranslationQuality($original, $translated) {
    $local_dict = getLocalDictionary();
    $cache_key = strtolower(trim($original));
    
    // Excellent: From local dictionary
    if (isset($local_dict[$cache_key])) {
        return 'excellent';
    }
    
    // Poor: No translation occurred
    if (strtolower($original) === strtolower($translated)) {
        return 'poor';
    }
    
    // Good: Contains Devanagari script (proper Hindi)
    if (preg_match('/[\x{0900}-\x{097F}]/u', $translated)) {
        return 'good';
    }
    
    // Fair: Some translation occurred but might be transliterated
    return 'fair';
}

/**
 * Get quality icon for translation
 * @param string $quality Quality level
 * @return string HTML icon
 */
function getQualityIcon($quality) {
    switch ($quality) {
        case 'excellent':
            return '<i class="fas fa-star text-success" title="Excellent (Local Dictionary)"></i>';
        case 'good':
            return '<i class="fas fa-check-circle text-primary" title="Good (API Translation)"></i>';
        case 'fair':
            return '<i class="fas fa-exclamation-circle text-warning" title="Fair (May need review)"></i>';
        case 'poor':
            return '<i class="fas fa-times-circle text-danger" title="Poor (No translation)"></i>';
        default:
            return '<i class="fas fa-question-circle text-muted"></i>';
    }
}

/**
 * Batch translate multiple items (more efficient for tables)
 * @param array $items Array of item names
 * @return array Array of translations
 */
function batchTranslateItems($items) {
    $translations = array();
    
    foreach ($items as $item) {
        $translations[] = getItemTranslation($item);
    }
    
    return $translations;
}

/**
 * Add CSS for translation display with quality indicators
 */
function addTranslationCSS() {
    echo '<style>
    .item-with-translation {
        line-height: 1.2;
    }
    
    .english-name {
        font-weight: 500;
        color: #333;
    }
    
    .hindi-name {
        font-size: 0.85em;
        font-style: italic;
        color: #666 !important;
        margin-top: 2px;
        display: flex;
        align-items: center;
    }
    
    .translation-quality {
        font-size: 0.75em;
        opacity: 0.8;
    }
    
    .translation-quality:hover {
        opacity: 1;
    }
    
    .dark-mode .english-name {
        color: #e2e8f0;
    }
    
    .dark-mode .hindi-name {
        color: #a0aec0 !important;
    }
    
    .item-with-tooltip {
        border-bottom: 1px dotted #999;
        cursor: help;
    }
    
    .translation-toggle {
        font-size: 0.875rem;
        margin-bottom: 10px;
    }
    
    .hindi-translation {
        background: linear-gradient(135deg, #ff9a56 0%, #ffad56 100%);
        color: white;
        border: none;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        margin-left: 5px;
    }
    
    .hindi-translation:hover {
        background: linear-gradient(135deg, #ff8a40 0%, #ff9a40 100%);
        color: white;
    }
    
    .translation-stats {
        background: #f8f9fa;
        border-left: 4px solid #007bff;
        padding: 10px;
        margin: 10px 0;
        font-size: 0.875rem;
    }
    
    .api-status {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 5px;
    }
    
    .api-status.online {
        background-color: #28a745;
    }
    
    .api-status.offline {
        background-color: #dc3545;
    }
    
    .api-status.unknown {
        background-color: #ffc107;
    }
    </style>';
}

/**
 * Display field value with Hindi translation (for locations, material types, purposes, etc.)
 * @param string $field_value The field value in English
 * @param bool $show_both Whether to show both languages or just English with hover
 * @return string HTML formatted field value
 */
function displayFieldWithTranslation($field_value, $show_both = true) {
    if (empty($field_value)) {
        return '';
    }
    
    $translation = getItemTranslation($field_value);
    $quality = getTranslationQuality($field_value, $translation['hindi']);
    
    if ($show_both && $translation['hindi'] !== $translation['english']) {
        $quality_icon = getQualityIcon($quality);
        return '<div class="field-with-translation">' .
               '<div class="english-name">' . htmlspecialchars($translation['english']) . '</div>' .
               '<div class="hindi-name text-muted small">' . 
               htmlspecialchars($translation['hindi']) . 
               ' <span class="translation-quality ms-1" title="Translation Quality: ' . ucfirst($quality) . '">' . 
               $quality_icon . '</span></div>' .
               '</div>';
    } else {
        // Show English with Hindi as tooltip
        return '<span class="field-with-tooltip" data-bs-toggle="tooltip" title="Hindi: ' . 
               htmlspecialchars($translation['hindi']) . '">' . 
               htmlspecialchars($translation['english']) . '</span>';
    }
}

/**
 * Display person name with Hindi translation (for created by, approved by fields)
 * @param string $person_name The person name
 * @param bool $show_both Whether to show both languages or just English with hover
 * @return string HTML formatted person name
 */
function displayPersonWithTranslation($person_name, $show_both = false) {
    if (empty($person_name)) {
        return '';
    }
    
    // For person names, we typically don't translate but can transliterate to Devanagari if needed
    // For now, just return the name as is since names are usually proper nouns
    return '<div class="person-name">' . htmlspecialchars($person_name) . '</div>';
}

/**
 * Display location with Hindi translation and formatting
 * @param string $location The location in English
 * @return string HTML formatted location
 */
function displayLocationWithTranslation($location) {
    return displayFieldWithTranslation($location, true);
}

/**
 * Display material type with Hindi translation and formatting
 * @param string $material_type The material type in English
 * @return string HTML formatted material type
 */
function displayMaterialTypeWithTranslation($material_type) {
    return displayFieldWithTranslation($material_type, true);
}

/**
 * Display purpose with Hindi translation and formatting
 * @param string $purpose The purpose in English
 * @return string HTML formatted purpose
 */
function displayPurposeWithTranslation($purpose) {
    return displayFieldWithTranslation($purpose, true);
}

/**
 * Add CSS for field translations (extends the existing translation CSS)
 */
function addFieldTranslationCSS() {
    echo '<style>
    .field-with-translation {
        margin-bottom: 5px;
    }
    
    .field-with-translation .english-name {
        font-weight: 500;
        color: #333;
        font-size: 1em;
        line-height: 1.4;
    }
    
    .field-with-translation .hindi-name {
        font-size: 0.875em;
        color: #6c757d !important;
        font-style: italic;
        line-height: 1.3;
        margin-top: 2px;
    }
    
    .field-with-tooltip {
        border-bottom: 1px dotted #6c757d;
        cursor: help;
    }
    
    .person-name {
        font-weight: 500;
        color: #333;
    }
    
    .translation-quality {
        opacity: 0.7;
    }
    
    .translation-quality:hover {
        opacity: 1;
    }
    
    /* Field-specific styling */
    .location-field .field-with-translation {
        background: #f8f9fa;
        padding: 8px;
        border-radius: 4px;
        border-left: 3px solid #007bff;
    }
    
    .material-type-field .field-with-translation {
        background: #fff3cd;
        padding: 8px;
        border-radius: 4px;
        border-left: 3px solid #ffc107;
    }
    
    .purpose-field .field-with-translation {
        background: #d1ecf1;
        padding: 8px;
        border-radius: 4px;
        border-left: 3px solid #17a2b8;
    }
    </style>';
}
?>
