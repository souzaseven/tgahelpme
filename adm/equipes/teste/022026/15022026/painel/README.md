# 📞 Painel Administrativo Evolux CX

Painel completo para gerenciamento da API Evolux CX com interface moderna e intuitiva.

## ✨ Funcionalidades

### 🎯 Módulos Principais

- **Dashboard** - Visão geral do sistema em tempo real
- **Agentes** - Gerenciamento completo de agentes
- **CallCenter** - Dashboard de métricas do callcenter
- **Chamadas** - Histórico e controle de chamadas
- **CDR** - Relatórios de chamadas detalhados
- **Discador** - Gerenciamento de campanhas
- **Filas** - Configuração e monitoramento de filas
- **PBX** - Ramais, troncos e rotas
- **Realtime** - Monitoramento em tempo real
- **Relatórios** - Relatórios analíticos
- **Tarefas** - Gerenciamento de tarefas
- **Usuários** - Controle de acesso
- **Chat** - Gestão de conversas

### 🚀 Recursos

✅ Interface responsiva e moderna  
✅ Monitoramento em tempo real  
✅ Gestão completa de agentes  
✅ Controle de filas e chamadas  
✅ Relatórios detalhados  
✅ Painel de métricas  
✅ Sistema de notificações  
✅ Auto-refresh configurável  

## 📋 Requisitos

- PHP 7.4 ou superior
- Apache/Nginx
- Extensão PHP cURL
- Token de API Evolux válido

## 🔧 Instalação

### 1. Clone ou baixe o projeto

```bash
# Opção 1: Via Git
git clone https://github.com/seu-usuario/evolux-admin-panel.git

# Opção 2: Download direto
# Baixe e extraia o arquivo ZIP
```

### 2. Configure seu servidor web

#### Apache

Aponte o DocumentRoot para a pasta `public`:

```apache
<VirtualHost *:80>
    ServerName evolux.local
    DocumentRoot /caminho/para/evolux-admin-panel/public
    
    <Directory /caminho/para/evolux-admin-panel/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name evolux.local;
    root /caminho/para/evolux-admin-panel/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 3. Configure a API

Edite o arquivo `config/api.php`:

```php
<?php
return [
    'base_url' => 'https://sua-instancia.evolux.io',
    'token'    => 'seu-token-aqui',
    'timeout'  => 15
];
```

### 4. Configurar permissões

```bash
chmod -R 755 evolux-admin-panel/
chown -R www-data:www-data evolux-admin-panel/
```

### 5. Acesse o painel

Abra seu navegador e acesse:
```
http://evolux.local
```

## 📁 Estrutura do Projeto

```
evolux-admin-panel/
├── app/
│   └── EvoluxAPI.php          # Classe principal da API
├── assets/
│   ├── css/
│   │   └── style.css          # Estilos do painel
│   └── js/
│       └── app.js             # JavaScript principal
├── config/
│   └── api.php                # Configurações da API
└── public/
    ├── index.php              # Arquivo principal
    └── pages/                 # Páginas do sistema
        ├── dashboard.php      # Dashboard principal
        ├── agentes.php        # Gestão de agentes
        ├── callcenter.php     # Dashboard callcenter
        ├── realtime.php       # Monitoramento realtime
        └── ...
```

## 🎨 Personalização

### Alterar cores do tema

Edite as variáveis CSS em `assets/css/style.css`:

```css
:root {
    --primary: #2563eb;        /* Cor primária */
    --success: #10b981;        /* Cor de sucesso */
    --danger: #ef4444;         /* Cor de erro */
    --warning: #f59e0b;        /* Cor de aviso */
}
```

### Adicionar novos módulos

1. Crie um novo arquivo em `public/pages/nome-modulo.php`
2. Adicione o link no menu de navegação em `public/index.php`
3. Implemente a lógica usando a classe `EvoluxAPI`

## 🔌 API - Métodos Disponíveis

### Agentes
```php
$api->getAgentes()
$api->getAgente($id)
$api->createAgente($data)
$api->updateAgente($id, $data)
$api->deleteAgente($id)
$api->pausarAgente($id, $motivo)
$api->despausarAgente($id)
```

### Chamadas
```php
$api->getChamadas($filtros)
$api->getChamada($id)
$api->originarChamada($data)
$api->transferirChamada($id, $data)
$api->desligarChamada($id)
```

### Filas
```php
$api->getFilas($filtros)
$api->getFila($id)
$api->createFila($data)
$api->updateFila($id, $data)
$api->deleteFila($id)
$api->getFilaMembros($id)
```

### Realtime
```php
$api->getRealtimeStatus()
$api->getRealtimeAgentes()
$api->getRealtimeFilas()
$api->getRealtimeChamadas()
$api->getRealtimeCanais()
```

### Relatórios
```php
$api->getRelatorioAtendimento($filtros)
$api->getRelatorioAgentes($filtros)
$api->getRelatorioFilas($filtros)
$api->getRelatorioChamadas($filtros)
```

## 🛠️ Solução de Problemas

### Erro de conexão com a API

Verifique:
- Se a URL base está correta em `config/api.php`
- Se o token está válido
- Se a extensão cURL está habilitada no PHP

```bash
php -m | grep curl
```

### Erros de permissão

```bash
chmod -R 755 evolux-admin-panel/
chown -R www-data:www-data evolux-admin-panel/
```

### Cache de configuração

Limpe o cache do PHP se fizer alterações na configuração:

```bash
# Apache
sudo service apache2 restart

# Nginx
sudo service nginx restart
sudo service php7.4-fpm restart
```

## 📊 Capturas de Tela

*(Aqui você pode adicionar screenshots do painel)*

## 🤝 Contribuindo

Contribuições são bem-vindas! Sinta-se à vontade para:

1. Fork o projeto
2. Criar uma branch para sua feature (`git checkout -b feature/NovaFuncionalidade`)
3. Commit suas mudanças (`git commit -m 'Adiciona nova funcionalidade'`)
4. Push para a branch (`git push origin feature/NovaFuncionalidade`)
5. Abrir um Pull Request

## 📝 Licença

Este projeto está sob a licença MIT. Veja o arquivo LICENSE para mais detalhes.

## 📞 Suporte

Para suporte, entre em contato através de:
- Email: suporte@seudominio.com
- Documentação Evolux: https://docs.evolux.io

## 🎯 Roadmap

- [ ] Implementar autenticação de usuários
- [ ] Adicionar exportação de relatórios em PDF/Excel
- [ ] Criar dashboard com gráficos interativos
- [ ] Implementar notificações em tempo real via WebSocket
- [ ] Adicionar sistema de permissões por módulo
- [ ] Criar API REST para integração com outros sistemas

---

Desenvolvido com ❤️ para gerenciamento Evolux CX
