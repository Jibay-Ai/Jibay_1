<?php
header('Content-Type: application/json; charset=utf-8');

$text = isset($_GET['text']) ? trim($_GET['text']) : '';
if ($text === '') {
    echo json_encode(["error" => "Text parameter not provided."], JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenizerPath = 'tokenizer.json';
if (!file_exists($tokenizerPath)) {
    echo json_encode(["error" => "tokenizer.json file not found."], JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenizer = json_decode(file_get_contents($tokenizerPath), true);
if (!$tokenizer) {
    echo json_encode(["error" => "Error reading tokenizer.json."], JSON_UNESCAPED_UNICODE);
    exit;
}

$allChars = $tokenizer['letters'] ?? [];
if (isset($tokenizer['additional_characters'])) {
    foreach ($tokenizer['additional_characters'] as $set) {
        if (is_array($set)) {
            $allChars = array_merge($allChars, $set);
        }
    }
}

$valid = false;
foreach ($allChars as $ch) {
    if (mb_strpos($text, $ch) !== false) {
        $valid = true;
        break;
    }
}

if (!$valid) {
    echo json_encode(["error" => "Text does not contain any valid letters or characters."], JSON_UNESCAPED_UNICODE);
    exit;
}

$models = [];
for ($i = 1; $i <= 8; $i++) {
    $pathJson = "model-jibay1-000{$i}.json";
    $pathJsonl = "model-jibay1-000{$i}.jsonl";
    if (file_exists($pathJson)) $models[] = $pathJson;
    elseif (file_exists($pathJsonl)) $models[] = $pathJsonl;
}

if (empty($models)) {
    echo json_encode(["error" => "No model files found."], JSON_UNESCAPED_UNICODE);
    exit;
}

function similarity($a, $b) {
    similar_text($a, $b, $percent);
    return $percent;
}

$englishGreetings = [
    "Dear", "Bro", "Your answer", "Friend", "My flower", "My good friend", "My dear", "Respected user",
    "Valued companion", "My brother", "My lovely sister", "Sir", "My lord", "Good boy", "My queen", "Sweetheart",
    "Cool friend", "My sympathizer", "Old friend", "Dear friend", "Dad's flower", "Dear soul", "Champion", "Smart one",
    "Master", "My intelligent", "Good colleague", "Lovely", "Smart", "Noble one", "My dear companion",
    "Hero", "Intelligent one", "Good-natured flower", "Specially smart", "Smart friend", "Genius", "Your intelligence",
    "Flower friend", "Little smart", "Cute smart", "My smart one", "Special intelligence", "Kind smart", "Real smart",
    "Awesome smart", "Special smart", "Professional smart", "Golden smart", "Smartest", "My dear smart",
    "Amazingly smart", "Lovable smart", "Flower smart", "Genius smart", "Kind smart",
    "Amazing smart", "Legendary smart", "Charming smart", "Beautiful smart", "My special smart", "Cute smart",
    "Cool smart", "Excellent smart", "Unique smart", "Creative smart", "Sharp-eyed smart", "Sweet smart",
    "Strong smart", "Civilized smart", "Understanding smart", "Logical smart", "Professional smart", "Fantastic smart",
    "My flower smart", "Pleasant smart", "Well-mannered smart", "Cool smart", "First-class smart", "Skilled smart",
    "Respected smart", "Cute smart", "Sweet smart", "Dear smart", "Special smart", "Beloved smart",
    "Beautiful smart", "Strong-hearted smart", "Polite smart", "Heartfelt smart", "My professional smart"
];

$englishFarewells = [
    "If you have any questions, ask 😊", "I'm at your service 🌹", "If you have a question, ask my dear 🙌", "Always at your service ❤️",
    "Ready for your next question 😉", "If you need any other help, let me know 🌷", "If you have another question, I'm here 😎",
    "If something was unclear, ask 😌", "Proudly at your service 💪", "Ask again, friend 🧠",
    "Waiting for your next question 📘", "If the answer wasn't enough, let me know 👂", "I'm always ready 🔥",
    "At the service of my dear student 🎓", "My good friend, ask again 🤝", "Whenever you want, ask 💬",
    "I'm ready 🌈", "Ask again 🌟", "Need help? I'm here 🧩", "Your questions are excellent 💎",
    "Serving Iranian intelligence 🇮🇷", "Come visit again 😊", "Until next question 👋", "Always here to help 💡",
    "Happy to answer 🌸", "Next question please 😄", "If you want to continue 🚀",
    "Serving science 💫", "Hope to see you again 📚", "Waiting for the next question 🌼",
    "At your service 🌹", "Your questions help growth 🌱", "Continue, you're doing great 💪",
    "Write again 🌻", "At your service 🙏", "Excellent question 👏", "Next question is ready 🔔",
    "Serving your learning 📖", "Ask again so we can learn 🌟", "You asked excellently 🌸",
    "Always at your service ❤️", "Ask again, professor 👨‍🏫", "Happy to answer with pleasure 😍",
    "Always ready to answer 🌙", "Serving the knowledgeable 💎", "Continue to become stronger 💪",
    "Ask again, dear 🌼", "Serving the love of knowledge ❤️", "Ready to help anytime 🌟"
];

$persianGreetings = [
    "عزیزم", "داداش", "جواب شما", "رفیق", "گل من", "دوست خوبم", "عزیز دلم", "کاربر محترم",
    "همراه گرامی", "برادر من", "خواهر گلم", "جناب", "سرورم", "گل پسر", "ملکه من", "عزیز دل",
    "رفیق باصفا", "همدل من", "یار قدیمی", "دوست نازنین", "گل بابا", "عزیز جان", "پهلوان", "باهوش",
    "استاد", "باهوش من", "همکار خوبم", "دوست داشتنی", "باهوش", "بزرگوار", "هم‌راه عزیزم",
    "قهرمان", "باهوشی", "گل خوش‌اخلاق", "باهوشی خاص", "رفیق باهوش", "نابغه", "باهوشی تو",
    "دوست گل", "باهوش کوچولو", "باهوش ناز", "باهوش منی", "باهوشی خاص", "باهوش مهربون", "باهوش واقعی",
    "باهوش خفن", "باهوش خاص", "باهوش حرفه‌ای", "باهوش طلایی", "باهوش ترین", "باهوش عزیزم",
    "باهوش فوق‌العاده", "باهوش دوست‌داشتنی", "باهوش گل", "باهوش نابغه", "باهوش مهربان",
    "باهوش شگفت‌انگیز", "باهوش افسانه‌ای", "باهوش دلبر", "باهوش قشنگ", "باهوش خاصم", "باهوش بانمک",
    "باهوش باحال", "باهوش عالی", "باهوش بی‌نظیر", "باهوش خوش‌فکر", "باهوش تیزبین", "باهوش نازنین",
    "باهوش قوی", "باهوش متمدن", "باهوش فهیم", "باهوش منطقی", "باهوش حرفه‌ای", "باهوش معرکه",
    "باهوش گل من", "باهوش دلپذیر", "باهوش بامرام", "باهوش باصفا", "باهوش درجه یک", "باهوش کاربلد",
    "باهوش محترم", "باهوش بانمک", "باهوش شیرین", "باهوش جان", "باهوش خاصی", "باهوش دلبند",
    "باهوش قشنگ", "باهوش قوی‌دل", "باهوش مودب", "باهوش اهل دل", "باهوش حرفه‌ای من"
];

$persianFarewells = [
    "اگر سوالی بود بپرس 😊", "در خدمتت هستم 🌹", "سوالی بود بپرس عزیزم 🙌", "درخدمتم همیشه ❤️",
    "آماده سوال بعدی‌ام 😉", "اگه کمک دیگه‌ای خواستی بگو 🌷", "بازم سوال داشتی من اینجام 😎",
    "اگه چیزی مبهم بود بپرس 😌", "با افتخار در خدمتتم 💪", "بازم بپرس رفیق 🧠",
    "منتظر سوال بعدی‌ات هستم 📘", "اگه جواب کافی نبود، بگو 👂", "همیشه آماده‌ام 🔥",
    "در خدمت دانشجوی عزیزم 🎓", "دوست خوبم، باز هم بپرس 🤝", "هر وقت خواستی بپرس 💬",
    "من آماده‌ام 🌈", "بازم بپرس 🌟", "کمک می‌خوای؟ من هستم 🧩", "سوالاتت عالی‌ان 💎",
    "در خدمت هوش ایرانی 🇮🇷", "بازم بیا سر بزن 😊", "تا سوال بعدی 👋", "همیشه اینجام برای کمک 💡",
    "با لذت جواب میدم 🌸", "سوال بعدی لطفاً 😄", "اگه خواستی ادامه بده 🚀",
    "در خدمت علم 💫", "به امید دیدار دوباره 📚", "منتظر سوال بعدی 🌼",
    "در خدمت شما 🌹", "پرسش‌هات باعث رشد میشه 🌱", "ادامه بده، خوب پیش میری 💪",
    "بازم بنویس 🌻", "درخدمت شما هستم 🙏", "پرسش عالی بود 👏", "سوال بعدی آماده‌ست 🔔",
    "در خدمت یادگیری تو 📖", "باز هم بپرس تا یاد بگیریم 🌟", "عالی پرسیدی 🌸",
    "درخدمت تو همیشه ❤️", "بازم بپرس استاد 👨‍🏫", "با کمال میل پاسخ میدم 😍",
    "همیشه آماده پاسخ دادن 🌙", "در خدمت اهل دانایی 💎", "ادامه بده تا قوی‌تر شی 💪",
    "بازم سوال کن عزیز 🌼", "در خدمت عشق به دانش ❤️", "هر لحظه آماده کمکم 🌟"
];

$isEnglish = preg_match('/[a-zA-Z]/', $text);
$greetings = $isEnglish ? $englishGreetings : $persianGreetings;
$farewells = $isEnglish ? $englishFarewells : $persianFarewells;

$bestMatch = null;
$bestScore = 0;

foreach ($models as $file) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $content = file_get_contents($file);
    if (!$content) continue;

    if ($ext === 'jsonl') {
        $lines = explode("\n", trim($content));
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (!$data) continue;
            $q = $data['question'] ?? $data['user'] ?? '';
            $a = $data['answer'] ?? $data['assistant'] ?? '';
            if (is_array($a)) $a = implode(' | ', $a);

            $score = similarity($text, $q);
            if ($score > $bestScore && $score >= 80) {
                $bestScore = $score;
                $bestMatch = ["question" => $q, "answer" => $a];
            }
        }
    } else {
        $json = json_decode($content, true);
        if (!$json) continue;

        if (isset($json[0]['user'])) {
            foreach ($json as $item) {
                $q = $item['user'] ?? '';
                $a = $item['assistant'] ?? '';
                $score = similarity($text, $q);
                if ($score > $bestScore && $score >= 80) {
                    $bestScore = $score;
                    $bestMatch = ["question" => $q, "answer" => $a];
                }
            }
        }
        elseif (isset($json['data'][0]['paragraphs'][0]['qas'])) {
            foreach ($json['data'] as $block) {
                foreach ($block['paragraphs'] as $p) {
                    foreach ($p['qas'] as $qa) {
                        $q = $qa['question'] ?? '';
                        $answers = [];
                        foreach ($qa['answers'] ?? [] as $ans) {
                            if (isset($ans['text'])) $answers[] = $ans['text'];
                        }
                        $a = implode(' | ', $answers);
                        $score = similarity($text, $q);
                        if ($score > $bestScore && $score >= 80) {
                            $bestScore = $score;
                            $bestMatch = ["question" => $q, "answer" => $a];
                        }
                    }
                }
            }
        }
        elseif (isset($json['data'][0]['questions'])) {
            foreach ($json['data'] as $block) {
                foreach ($block['questions'] as $qitem) {
                    $q = $qitem['input_text'] ?? '';
                    $matchAnswer = '';
                    foreach ($block['answers'] as $ans) {
                        if (($ans['turn_id'] ?? null) === ($qitem['turn_id'] ?? null)) {
                            $matchAnswer = $ans['input_text'] ?? ($ans['span_text'] ?? '');
                            break;
                        }
                    }
                    $score = similarity($text, $q);
                    if ($score > $bestScore && $score >= 80) {
                        $bestScore = $score;
                        $bestMatch = ["question" => $q, "answer" => $matchAnswer];
                    }
                }
            }
        }
    }
}

if ($bestMatch) {
    $greet = $greetings[array_rand($greetings)];
    $farewell = $farewells[array_rand($farewells)];
    $finalAnswer = trim("{$greet}، {$bestMatch['answer']} {$farewell}");

    echo json_encode([
        [
            "thinking" => "Well, the user said '{$text}'. I should try to review, collect, analyze and present the best information to the user, the best answer is ('{$finalAnswer}').",
            "question" => $bestMatch['question'],
            "answer" => $finalAnswer
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode(["message" => "No suitable answer found."], JSON_UNESCAPED_UNICODE);
}
?>