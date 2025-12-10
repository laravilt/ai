<?php

return [
    // Global Search
    'search' => [
        'placeholder' => 'بحث...',
        'ai_powered' => 'بحث مدعوم بالذكاء الاصطناعي',
        'no_results' => 'لا توجد نتائج',
        'no_results_for' => 'لا توجد نتائج لـ ":query"',
        'try_different' => 'حاول البحث بكلمات مختلفة',
        'start_typing' => 'ابدأ الكتابة للبحث',
        'results_count' => ':count نتائج',
        'type_to_search' => 'اكتب للبحث',
        'press_to_close' => 'اضغط :key للإغلاق',
        'to_navigate' => 'للتنقل',
        'to_select' => 'للاختيار',
        'to_close' => 'للإغلاق',
        'toggle_ai' => 'تبديل بحث الذكاء الاصطناعي',
        'ai_enabled' => 'بحث الذكاء الاصطناعي مفعل',
        'ai_disabled' => 'بحث الذكاء الاصطناعي معطل',
    ],

    // Chat
    'chat' => [
        'title' => 'محادثة الذكاء الاصطناعي',
        'new_chat' => 'محادثة جديدة',
        'send_message' => 'إرسال الرسالة',
        'type_message' => 'اكتب رسالتك...',
        'thinking' => 'جاري التفكير...',
        'error' => 'حدث خطأ. يرجى المحاولة مرة أخرى.',
        'sessions' => 'جلسات المحادثة',
        'no_sessions' => 'لا توجد جلسات محادثة',
        'delete_session' => 'حذف الجلسة',
        'delete_confirm' => 'هل أنت متأكد من حذف هذه الجلسة؟',
        'clear_chat' => 'مسح المحادثة',
        'clear_confirm' => 'هل أنت متأكد من مسح هذه المحادثة؟',
        'copy_message' => 'نسخ الرسالة',
        'copied' => 'تم النسخ',
        'regenerate' => 'إعادة توليد الرد',
    ],

    // Providers
    'providers' => [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic (Claude)',
        'gemini' => 'Google Gemini',
        'deepseek' => 'DeepSeek',
        'select_provider' => 'اختر مزود الذكاء الاصطناعي',
        'select_model' => 'اختر النموذج',
    ],

    // Settings
    'settings' => [
        'title' => 'إعدادات الذكاء الاصطناعي',
        'provider' => 'مزود الذكاء الاصطناعي',
        'model' => 'النموذج',
        'temperature' => 'درجة الحرارة',
        'max_tokens' => 'الحد الأقصى للرموز',
    ],

    // Actions
    'actions' => [
        'ask_ai' => 'اسأل الذكاء الاصطناعي',
        'generate' => 'توليد',
        'generating' => 'جاري التوليد...',
        'improve' => 'تحسين بالذكاء الاصطناعي',
        'translate' => 'ترجمة',
        'summarize' => 'تلخيص',
        'explain' => 'شرح',
    ],

    // Errors
    'errors' => [
        'not_configured' => 'الذكاء الاصطناعي غير مُهيأ. يرجى إضافة مفتاح API الخاص بك.',
        'provider_error' => 'خطأ في الاتصال بمزود الذكاء الاصطناعي.',
        'rate_limit' => 'تم تجاوز حد المعدل. يرجى المحاولة لاحقاً.',
        'invalid_response' => 'استجابة غير صالحة من مزود الذكاء الاصطناعي.',
    ],
];
