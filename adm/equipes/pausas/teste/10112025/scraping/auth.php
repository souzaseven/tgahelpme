<?php
class EvoluxAuth {
    private $username;
    private $password;
    private $cookiesFile = 'cookies.txt';
    
    public function __construct($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }
    
    public function login() {
        // PRECISAMOS DESCOBRIR A URL EXATA DE LOGIN E OS CAMPOS
        $loginUrl = 'https://tgasistemas.evolux.io/login'; // PODE PRECISAR AJUSTAR
        
        // ESTES CAMPOS PRECISAM SER AJUSTADOS CONFORME O FORMULÁRIO REAL
        $postData = http_build_query([
            'username' => $this->username,
            'password' => $this->password,
            'csrf_token' => $this->getCSRFToken() // PRECISAREMOS IMPLEMENTAR
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $loginUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR => $this->cookiesFile,
            CURLOPT_COOKIEFILE => $this->cookiesFile,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200 || $httpCode === 302;
    }
    
    private function getCSRFToken() {
        // IMPLEMENTAR LÓGICA PARA OBTER TOKEN CSRF
        // POR ENQUANTO, RETORNA VAZIO
        return '';
    }
    
    public function getCookiesFromFile() {
        if (!file_exists($this->cookiesFile)) {
            return false;
        }
        
        $cookies = [];
        $lines = file($this->cookiesFile);
        
        foreach ($lines as $line) {
            if (trim($line) === '' || $line[0] === '#') continue;
            
            $parts = explode("\t", $line);
            if (count($parts) >= 7) {
                $cookies[] = $parts[5] . '=' . $parts[6];
            }
        }
        
        return implode('; ', $cookies);
    }
    
    public function isLoggedIn() {
        $testUrl = 'https://tgasistemas.evolux.io/panel/queue?id=all';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $testUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEFILE => $this->cookiesFile,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HEADER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200 && strpos($response, 'login') === false;
    }
}
?>