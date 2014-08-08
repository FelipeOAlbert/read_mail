<?php

    if (!extension_loaded('imap')) {
        die('Modulo PHP/IMAP nao foi carregado');
    }

    class read_mail{
        
        private $servidor, $login, $senha, $host, $mysql_user, $mysql_password, $db, $link;
        public $conexao;
        
        function __construct ()
        {
            $this->servidor         = '{imap.gmail.com:993/imap/ssl}';
            $this->login            = '';
            $this->senha            = '';
            $this->host             = 'localhost';
            $this->mysql_user       = 'root';
            $this->mysql_password   = '123qwe';
            $this->db               = 'read_mail';
            
            $this->conecta_mysql();
            
            die();
            
            $this->conecta_email();
            $this->salva_email();
        }
        
        function conecta_email()
        {
            $this->conexao = imap_open($this->servidor, $this->login, $this->senha);
            
            if (!$this->conexao){
                die('Erro ao conectar: '.imap_last_error());
            }
        }
        
        function salva_email()
        {
            $check = imap_check($this->conexao);
            
            $emails = array();
            
            if($check){
                
                for($i = 1; $i <= $check->Nmsgs; $i++){
                    
                    $overview = imap_fetch_overview($this->conexao, $i);
                    
                    $email = current($overview);
                    
                    // Assunto
                    $emails[$i]['assunto'] = $email->subject;
                    
                    // Remetente
                    $emails[$i]['remetente'] = $email->from;
                    
                    // Data
                    $emails[$i]['data'] = $email->date;
                    
                    // Identificador da mensagem
                    $emails[$i]['message_id'] =  $email->message_id;
                    
                    // Identificador da mensagem na caixa de entrada
                    $emails[$i]['uid'] =  $email->uid;
                    
                    // corpo da mensagem
                    $emails[$i]['corpo'] =  quoted_printable_decode(imap_body($this->conexao, $i));
                    
                    $this->downloadAnexo($i, $email->uid);
                }
                
                
                
                $this->printr($emails);
            }
        }
        
        function conecta_mysql()
        {
            echo utf8_decode('Conectando ao servidor de banco de dados...<br><br>');
            
            // conectando no mysql
            $this->link = mysql_connect($this->host, $this->mysql_user, $this->mysql_password);
            
            if (!$this->link) {
                die(utf8_decode('Não foi possível conectar: ' . mysql_error()));
            }
            
            echo utf8_decode('Conexão bem sucedida...<br><br>');
            
            // consultando banco de dados
            echo utf8_decode('Verificando se banco de dados '.$this->db.' está criado<br><br>');
            $db_list = mysql_list_dbs($this->link);
            
            while ($row = mysql_fetch_array($db_list))
            {
               $return[] = $row[0];
            }
            
            if(in_array($this->db, $return)){
                
                echo utf8_decode('Banco de dados encontrado<br><br>Verificando se tabela email_data está criada..<br><br>');
                
                // verifica se tem a tabela
                $sql = "SHOW TABLES FROM ".$this->db." LIKE 'email_data'";
                
                $result = mysql_query($sql, $this->link);
                $result = mysql_fetch_assoc($result);
                
                if(!$result){
                    
                    echo utf8_decode('Tabela email_data não encontada<br><br>Criando tabela<br><br>');
                    
                    mysql_select_db($this->db, $this->link);
                    
                    $sql = "CREATE TABLE IF NOT EXISTS `email_data` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `assunto` text NOT NULL,
                        `remetente` text NOT NULL,
                        `data` text NOT NULL,
                        `message_id` text NOT NULL,
                        `uid` text NOT NULL,
                        `corpo` text NOT NULL,
                        `created_in` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1";
                        
                    if (mysql_query($sql, $this->link)){
                        echo utf8_decode('Tabela criada com sucesso...<br><br>');
                        
                        return true;
                    }else{
                        die('Erro ao criar o tabela: ' . mysql_error());
                    }
                }
                
                echo utf8_decode('Tabela encontada<br><br>');
                
                return true;
            }else{
                
                echo utf8_decode('Banco de dados não encontrado, iniciando rotina para criar banco....<br><br>');
                
                $sql = 'CREATE DATABASE '.$this->db;
                
                if (mysql_query($sql, $this->link)){
                    echo utf8_decode("O banco de dados ".$this->db." foi criado<br> Criando tabela....<br><br>");
                    
                    mysql_select_db($this->db, $this->link);
                    
                    $sql = "CREATE TABLE IF NOT EXISTS `email_data` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `assunto` text NOT NULL,
                        `remetente` text NOT NULL,
                        `data` text NOT NULL,
                        `message_id` text NOT NULL,
                        `uid` text NOT NULL,
                        `corpo` text NOT NULL,
                        `created_in` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1";
                        
                    if (mysql_query($sql, $this->link)){
                        echo utf8_decode('Tabela criada com sucesso...<br><br>');
                        
                        return true;
                        
                    }else{
                        die('Erro ao criar o tabela: ' . mysql_error());
                    }
                }else{
                    die('Erro ao criar o banco de dados: ' . mysql_error());
                }
            }
        }
        
        function downloadAnexo($id, $uid)
        {
            $structure = imap_fetchstructure($this->conexao, $id);
            
            $attachments = array();
            
            if(isset($structure->parts) && count($structure->parts)){
                
                for($i = 0; $i < count($structure->parts); $i++){
                    
                    $attachments[$i] = array(
                        'is_attachment' => false,
                        'filename'      => '',
                        'name'          => '',
                        'attachment'    => ''
                    );
                    
                    if($structure->parts[$i]->ifdparameters){
                        
                        foreach($structure->parts[$i]->parameters as $object){
                            
                            if(strtolower($object->attribute) == 'filename'){
                                $attachments[$i]['is_attachment']   = true;
                                $attachments[$i]['filename']        = $object->value;
                            }
                        }
                    }
                    
                    if($structure->parts[$i]->ifparameters){
                        
                        foreach($structure->parts[$i]->parameters as $object){
                            
                            if(strtolower($object->attribute) == 'name'){
                                $attachments[$i]['is_attachment']   = true;
                                $attachments[$i]['name']            = $object->value;
                            }
                        }
                    }
                    
                    if($attachments[$i]['is_attachment']){
                        
                        $attachments[$i]['attachment'] = imap_fetchbody($this->conexao, $id, $i+1);
                        
                        /* 4 = QUOTED-PRINTABLE encoding */
                        if($structure->parts[$i]->encoding == 3){
                            $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                        }
                        /* 3 = BASE64 encoding */
                        elseif($structure->parts[$i]->encoding == 4){ 
                            $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                        }
                    }
                }
            }
            
            /* salvando na pasta*/
            foreach($attachments as $attachment){
                
                if($attachment['is_attachment'] == 1){
                    
                    $filename = $attachment['name'];
                    if(empty($filename)) $filename = $attachment['filename'];
                    
                    if(empty($filename)) $filename = time() . ".dat";
                    
                    file_put_contents('attachment/'.$uid . "-" . $filename, $attachment['attachment']);
                }
            }
        }
        
        function lista_caixa($todas = false)
        {
            $pastas     = array();
            $marcadores = imap_getmailboxes($this->conexao, $this->servidor, '*');
            
            $count = 0;
            
            if (is_array($marcadores)) {
                foreach ($marcadores as $marcador) {
                    
                    $nome   = str_replace($this->servidor, '', $marcador->name);
                    $pos    = strpos($nome, $marcador->delimiter);
                    
                    if ($pos !== false) {
                        $pastas[] = substr($nome, $pos + 1);
                    }elseif($todas !== false){
                        $pastas[] = $nome;
                    }
                }
            }else{
                die(imap_last_error());
            }
            
            return $pastas;
        }
        
        function printr($data)
        {
            print "<pre>";
            print_r($data);
            die();
        }
        
        function __destruct()
        {
            //imap_close($this->conexao);
        }
    }
    
    $read = new read_mail();
?>