# ElevenLabs-OpenAI-Integration

## 介绍
参考https://elevenlabs.io/docs/api-reference/websockets 中的python代码，
我写了一个php版本的
并且加了一个h5客户端界面，可以发语音跟gpt聊天，并输出克隆语音。
本项目通过巧妙地将ElevenLabs文本到语音服务与OpenAI GPT-3.5 Turbo聊天模型集成，
创建了一个动态而互动的环境。利用Workerman库实现了一个WebSocket服务器，
用于接收用户的文本输入，通过OpenAI GPT-3.5 Turbo模型进行聊天完成，
然后使用ElevenLabs文本到语音服务将响应转换为语音。
这样就可以与你自己克隆的声音进行聊天了。
## 先决条件

确保您的服务器满足以下先决条件：

- 已安装PHP8.0以上。
- 已安装Composer以管理项目依赖关系。
- 获取了OpenAI和ElevenLabs的API密钥。在代码中用实际的API密钥和语音ID替换 'your_openai_apikey'，'your_elevenlabs_apikey' 和 'your_voice_id'。

## 入门指南

1. 克隆仓库库：

    ```bash
    git clone https://github.com/xuebingwang/ElevenLabs-OpenAI-Integration.git
    ```

2. 安装依赖项：

    ```bash
    composer install
    ```

3. 替换占位符API密钥：

    - 在代码中使用实际的API密钥和语音ID替换 'your_openai_apikey'，'your_elevenlabs_apikey' 和 'your_voice_id'。

4. 运行WebSocket服务器：

    ```bash
    php socket_server.php
    ```

5. 使用支持WebSocket通信的客户端连接到WebSocket服务器。

## 使用方法

1. 使用WebSocket客户端连接到WebSocket服务器。
2. 发送具有以下格式的JSON消息：

    ```json
    {
        "token": "your_token"
    }
    ```
   ```json
    {
        "text": "your_text_to_generate_speech"
    }
   ```

    用身份验证令牌替换 'your_token'，用要转换为语音的文本替换 'your_text_to_generate_speech'。

3. 从服务器接收响应，其中包括由ElevenLabs生成的语音。

4. 具体效果的请看web_client.html and client.php。

## 特性
- 可以使用自己克隆的声音
- 将用户文本输入实时转换为自然音质的语音。
- 无缝集成OpenAI强大的聊天模型，实现动态对话。
- 可定制的WebSocket服务器，方便集成到各种应用中。
- 适用于需要互动和引人入胜的语音交互的项目。

## 许可证

本项目采用 [MIT许可证](LICENSE)。
