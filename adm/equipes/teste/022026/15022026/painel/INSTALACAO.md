# 🚀 GUIA RÁPIDO DE INSTALAÇÃO

## Instalação em 5 minutos

### 1️⃣ Extrair arquivos
Extraia o conteúdo do ZIP para o diretório do seu servidor web:
- Apache: `/var/www/html/evolux-admin`
- XAMPP/WAMP: `C:\xampp\htdocs\evolux-admin`

### 2️⃣ Configurar API
Edite o arquivo: `config/api.php`

```php
<?php
return [
    'base_url' => 'https://tgasistemas.evolux.io',  // Sua URL
    'token'    => '696d2008-bbd9-4869-a33d-8f186b843867',  // Seu token
    'timeout'  => 15
];
```

### 3️⃣ Acessar o painel
Abra no navegador:
```
http://localhost/evolux-admin/public
```
ou
```
http://seu-dominio.com/public
```

## ✅ Pronto!

O painel já está funcionando e pronto para uso!

---

## 🔧 Configuração Avançada (Opcional)

### Apache - Virtual Host

Crie um arquivo em `/etc/apache2/sites-available/evolux.conf`:

```apache
<VirtualHost *:80>
    ServerName evolux.local
    DocumentRoot /var/www/html/evolux-admin/public
    
    <Directory /var/www/html/evolux-admin/public>
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/evolux-error.log
    CustomLog ${APACHE_LOG_DIR}/evolux-access.log combined
</VirtualHost>
```

Ative o site:
```bash
sudo a2ensite evolux
sudo systemctl reload apache2
```

Adicione ao `/etc/hosts`:
```
127.0.0.1   evolux.local
```

Acesse: `http://evolux.local`

### Nginx - Server Block

```nginx
server {
    listen 80;
    server_name evolux.local;
    root /var/www/html/evolux-admin/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 🆘 Problemas Comuns

### Erro 403 - Forbidden
```bash
chmod -R 755 /var/www/html/evolux-admin
chown -R www-data:www-data /var/www/html/evolux-admin
```

### Erro de conexão com API
- Verifique se o token está correto em `config/api.php`
- Teste a URL base no navegador
- Verifique se o cURL está habilitado: `php -m | grep curl`

### Página em branco
- Ative os erros do PHP em `php.ini`:
```ini
display_errors = On
error_reporting = E_ALL
```
- Reinicie o Apache/Nginx
- Verifique os logs: `tail -f /var/log/apache2/error.log`

---

## 📞 Suporte

- Documentação completa: Veja README.md
- API Evolux: https://docs.evolux.io

---

**Desenvolvido para Evolux CX** ❤️
