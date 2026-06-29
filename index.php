<?php

    //token da api do field control
    $field_token = "OWI1ZGY1NjQtNmMxNC00N2VmLTliOGYtZDExMWQ1YjljM2UwOjE5MTg3";

    //url da api do whatsapp
    $url_meu_node = "https://servidor-node-mtm.onrender.com/enviar-mensagem";

    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    //faz a verificação se recebeu data do field
    if (!$data) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Nenhum dado recebido."]);
        exit();
    }

    // verifica se ordem de servico recebida do field tem status == concluido
    if (isset($data['status']) && ($data['status'] === 'done')) {

        //pega id da OS da api do field
        $id_ordem_servico = $data['order']['id'] ?? null;

        //se não tiver ID na OS ele sai para não quebrar o código
        if (!$id_ordem_servico) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID da OS não encontrado no payload."]);
            exit();
        }
        //

        //----------------------------------------------------------------------------------------------//

        //primeira req no field, busca os IDs do cliente através da OS
        $url_field = "https://carchost.fieldcontrol.com.br/orders/" . $id_ordem_servico;
        
        $ch = curl_init($url_field);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer",
            "Content-Type: application/json",
            "X-Api-Key: " . $field_token
        ]);

        $response_field = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        //resposta do primeiro GET 
        if ($http_code === 200) {
            $dados_os = json_decode($response_field, true);
            $id_cliente = $dados_os['customer']['id'] ?? null;
            $id_servico = $dados_os['services']['id'] ?? null;
            
            if (!$id_cliente) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "ID do cliente não encontrado nos dados da OS."]);
                exit();
            }
        } else {
            //mostra erro retornado
            http_response_code(500);
            echo json_encode([
                "status" => "error", 
                "message" => "Erro ao consultar API do Field Control na busca do id do cliente.",
                "detalhes_do_erro" => [
                    "http_code_recebido" => $http_code,
                    "resposta_da_api" => json_decode($response_field, true) ?? $response_field
                ]
            ]);
            exit();
        }
        //fim da primeira req no field

        //--------------------------//

        //segunda req no field, busca as informações detalhadas do cliente
        $url_field1 = "https://carchost.fieldcontrol.com.br/customers/" . $id_cliente;
        
        $ch1 = curl_init($url_field1);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer",
            "Content-Type: application/json",
            "X-Api-Key: " . $field_token
        ]);

        $response_field1 = curl_exec($ch1);
        $http_code1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
        curl_close($ch1);
        
        // Resposta do segundo GET 
        if ($http_code1 === 200) {
            $dados_os1 = json_decode($response_field1, true);
            
            $nome_bruto = $dados_os1['name'] ?? '';
            $telefone_bruto = $dados_os1['contact']['phone'] ?? '';
            
            //deixa as iniciais em maiúsculo
            $nome_formatado = mb_convert_case($nome_bruto, MB_CASE_TITLE, "UTF-8");
            
            $telefone_limio = preg_replace('/[^0-9]/', '', $telefone_bruto);

            //remove código do país caso venha do Field Control
            if (substr($telefone_limio, 0, 2) === '55') {
                $telefone_limio = substr($telefone_limio, 2);
            }

            //se for celular com 11 dígitos (DDD + 9 + número)
            if (strlen($telefone_limio) === 11 && substr($telefone_limio, 2, 1) === '9') {
                $telefone_limio = substr($telefone_limio, 0, 2) . substr($telefone_limio, 3);
            }

            // Monta no padrão 55XXXXXXXXXX
            $telefone_whatsapp = "55" . $telefone_limio;
            
            $texto_mensagem = "
                Olá, *" . $nome_formatado . "*! Sua ordem de serviço foi concluída com sucesso e *sua opinião é muito importante para nós*! \n \n

                https://forms.gle/QYiLTVb3q9yHCJ4T6   

                \n \n 

                ```Mensagem automatica gerada pelo bot MTM```
            ";

            //envio de mensagem para o cliente
            $payload_node = [
                "phone"   => $telefone_whatsapp,
                "message" => $texto_mensagem
            ];

            //serviços permitidos
            $servicos_permitidos = [
                "MjY0MTc6MTkxODc=", 
                "Mjg0NzA6MTkxODc=", 
                "MjYxMTk6MTkxODc=", 
                "MjYxMjE6MTkxODc=", 
                "MjU2MDk6MTkxODc=", 
                "NTA0NTc3OjE5MTg3", 
                "MzYyNjQ4OjE5MTg3", 
                "MTQ1MTY3OjE5MTg3", 
                "OTYzNjg6MTkxODc="
            ];

            if(in_array($id_servico, $servicos_permitidos)){
                 $ch_node = curl_init($url_meu_node);
                curl_setopt($ch_node, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_node, CURLOPT_POST, true);
                curl_setopt($ch_node, CURLOPT_POSTFIELDS, json_encode($payload_node));
                curl_setopt($ch_node, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json"
                ]);

                $response_node = curl_exec($ch_node);
                $http_code_node = curl_getinfo($ch_node, CURLINFO_HTTP_CODE);
                curl_close($ch_node);

                //resposta unificada que api devolve
                http_response_code($http_code_node === 200 ? 200 : 500);
                echo json_encode([
                    "status" => $http_code_node === 200 ? "success" : "error",
                    "message" => "Envio processado pelo servidor Node.js.",
                    "dados_cliente" => [
                        "nome_formatado" => $nome_formatado,
                        "telefone_whatsapp" => $telefone_whatsapp 
                    ],
                    "resposta_do_servidor_node" => json_decode($response_node, true) ?? $response_node
                ], JSON_UNESCAPED_UNICODE);
                exit();
            }

            exit();

        } else {
            //mostra erro retornado
            http_response_code(500);
            echo json_encode([
                "status" => "error", 
                "message" => "Erro ao consultar API do Field Control na busca das informações do cliente.",
                "detalhes_do_erro" => [
                    "http_code_recebido" => $http_code,
                    "resposta_da_api" => json_decode($response_field, true) ?? $response_field
                ]
            ]);
            exit();
        }
        //fim da segunda req no field

        //--------------------------//

    } 
    else { //se não tiver concluída, ele dispara o trigger mas o evento não é finalizado
        http_response_code(200);
        echo json_encode(["status" => "ignored", "message" => "Evento recebido, mas a atividade nao possui status 'done'."]);
    }

?>