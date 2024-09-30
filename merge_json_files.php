<?php

function fixJsonMessagesString(string $mesages_file_string)
{
    $mesages_file_string = preg_replace_callback('/\\\\u00([a-f0-9]{2})/', function ($m) { return chr(hexdec($m[1])); } , $mesages_file_string);

    $decode = function ($data, $associative = true, $depth = 512, $options = 0)
    {
        $decoded = json_decode($data, $associative, $depth, $options);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw (new InvalidArgumentException("json_decode error: " . json_last_error_msg()));
        }
        return $decoded;
    };

    $encode = function ($data, $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT, $depth = 512)
    {
        $encoded = json_encode($data, $options, $depth);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw (new InvalidArgumentException("json_encode error: " . json_last_error_msg()));
        }
        return $encoded;
    };

    $json_struct = $decode($mesages_file_string);

    $phpVersion = substr(phpversion() , 0, 3) * 1;
    if ($phpVersion >= 5.4)
    {
        $encodedValue = $encode($json_struct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    else
    {
        $encodedValue = preg_replace('/\\\\u([a-f0-9]{4})/e', "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", $encode($json_struct, JSON_PRETTY_PRINT));
    }

    return $encodedValue;
}

function merge_json_files($directory) {
    // Pliki w odpowiedniej kolejności (od najstarszego do najnowszego)
    // $filenames = ['message_3.json', 'message_2.json', 'message_1.json'];

    // Wyszukiwanie wszystkich plików pasujących do wzorca message_*.json
    $files = glob($directory . DIRECTORY_SEPARATOR . 'message_*.json');
    
    // Filtrujemy i sortujemy pliki według numeru w nazwie (malejąco)
    usort($files, function($a, $b) {
        $numA = (int) filter_var(basename($a), FILTER_SANITIZE_NUMBER_INT);
        $numB = (int) filter_var(basename($b), FILTER_SANITIZE_NUMBER_INT);
        return $numB - $numA;
    });
    
    // Ustawiamy tylko nazwy plików w zmiennej $filenames (bez pełnych ścieżek)
    $filenames = array_map('basename', $files);

    $all_messages = [];
    $other_data = [];  // Zmienna, aby zachować inne sekcje JSON (np. participants)
    $key_order = [];   // Lista do przechowania oryginalnej kolejności kluczy

    // Wczytywanie plików
    foreach ($filenames as $index => $filename) {
        $filepath = $directory . DIRECTORY_SEPARATOR . $filename;
        
        if (!file_exists($filepath)) {
            echo "Plik $filename nie istnieje w katalogu $directory\n";
            return;
        }

        $json_content = file_get_contents($filepath);
        $data = json_decode($json_content, true);

        // Zapisz klucze tylko z pierwszego pliku (najstarszego)
        if ($index === 0) {
            $key_order = array_keys($data);
        }

        // Zapisujemy pozostałe sekcje (wszystkie poza 'messages') z pierwszego pliku
        if ($index === 0) {
            foreach ($data as $key => $value) {
                if ($key != 'messages') {
                    $other_data[$key] = $value;
                }
            }
        }

        // Każdy plik ma wiadomości od najnowszej do najstarszej, więc trzeba je odwrócić
        $all_messages = array_merge($all_messages, array_reverse($data['messages']));
    }

    // Tworzenie ostatecznej struktury JSON z zachowaniem kolejności kluczy z najstarszego pliku
    $result = [];

    // Najpierw kopiujemy wszystkie klucze z najstarszego pliku, zachowując ich oryginalną kolejność
    foreach ($key_order as $key) {
        if ($key == 'messages') {
            $result[$key] = $all_messages;
        } else {
            $result[$key] = isset($other_data[$key]) ? $other_data[$key] : null;
        }
    }

    // Zapisanie wynikowego pliku z kodowaniem ASCII
    $output_filepath = $directory . DIRECTORY_SEPARATOR . 'messages_full.json';

    file_put_contents($output_filepath, fixJsonMessagesString(json_encode($result, JSON_PRETTY_PRINT)));

    echo "Wynikowy plik zapisany jako: $output_filepath\n";
}

// Odczyt katalogu roboczego z parametru linii poleceń
if ($argc != 2) {
    echo "Użycie: php skrypt.php <katalog roboczy>\n";
    exit(1);
}

$working_directory = $argv[1];

if (!is_dir($working_directory)) {
    echo "Podany katalog nie istnieje: $working_directory\n";
    exit(1);
}

// Wywołanie funkcji
merge_json_files($working_directory);
