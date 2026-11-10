# Relatório — Enviando e-mail com PHP e a API do Resend

**Aluno:** Victoria De Moraes Villanes

## O que fiz em cada desafio

1. **Contato com cópia:** Desenvolvi um formulário que recebe nome, e-mail e mensagem. O destinatário principal fica configurado no `config.php`, o assunto é montado pelo PHP e o `reply_to` permite responder diretamente ao visitante. Também implementei o envio de uma confirmação automática para quem preencheu o formulário.

2. **Registro de envios:** Implementei o registro das tentativas de envio no arquivo `envios.log`, armazenando data, destinatário, código HTTP e ID do envio ou mensagem de erro. Utilizei `FILE_APPEND` para manter os registros anteriores e configurei a proteção do arquivo no `.gitignore` e no `.htaccess`, evitando que informações sensíveis sejam expostas.

3. **Tratamento de erros:** Criei mensagens em português para os erros 401, 403, 422, 429 e falhas de rede. Dessa forma, o usuário recebe uma mensagem fácil de entender, enquanto os detalhes técnicos ficam registrados no log. Também separei os erros de conexão dos erros retornados pela API.

4. **E-mail em HTML:** Desenvolvi um layout de e-mail utilizando tabelas HTML, estilos inline e largura máxima de 600px, com cabeçalho, corpo e rodapé. Utilizei `htmlspecialchars()` para proteger os dados inseridos pelo usuário e mantive o campo `text` como alternativa ao HTML. Também realizei os testes de visualização no Gmail e no Outlook, registrando os resultados nos prints.

**Extra — Anexos:** Implementei a validação de arquivos anexados, permitindo PDF, PNG e JPG, com limite de 2 MB. Também realizei a verificação do tipo real do arquivo para evitar o envio de formatos não permitidos.

## Qual erro deu mais trabalho

O erro 403 foi um dos que mais exigiram atenção durante os testes. Inicialmente, precisei entender como a API do Resend se comportava ao tentar enviar uma mensagem para um endereço que não estava autorizado no modo de teste. Para identificar a causa, consultei o registro de envios e verifiquei o código HTTP retornado pela API. Depois, ajustei o tratamento para apresentar uma mensagem em português, sem mostrar os detalhes técnicos ao usuário.

## Por que o config.php não está neste ZIP

O arquivo `config.php` contém a chave da API do Resend, que funciona como uma senha e permite realizar envios de e-mail. Por segurança, esse arquivo não foi incluído no ZIP. No lugar dele, foi utilizado o `config.exemplo.php`, contendo apenas as configurações necessárias, sem a chave privada.

## Conclusão

Com o desenvolvimento deste projeto, consegui praticar o envio de e-mails utilizando PHP e uma API externa, além de trabalhar com validação de dados, registro de erros, segurança e organização do código. Também aprendi a testar diferentes situações e a apresentar mensagens mais claras para o usuário.