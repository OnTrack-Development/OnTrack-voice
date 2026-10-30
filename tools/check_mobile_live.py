"""Live session + WebSocket setup check; never log or save ephemeral tokens."""
import base64
import hashlib
import json
import os
from pathlib import Path
import socket
import ssl
import struct
import urllib.parse
import urllib.request


def read_exact(stream, count):
    data = b''
    while len(data) < count:
        part = stream.recv(count-len(data))
        if not part:
            raise EOFError('WebSocket ended')
        data += part
    return data


def send(stream, payload, opcode=1):
    mask = os.urandom(4)
    size = len(payload)
    header = bytes([0x80 | opcode])
    header += bytes([0x80 | size]) if size < 126 else bytes([0xfe])+struct.pack('!H',size) if size < 65536 else bytes([0xff])+struct.pack('!Q',size)
    stream.sendall(header+mask+bytes(v ^ mask[i%4] for i,v in enumerate(payload)))


def attempt(encoded=True, version="v1alpha", method="BidiGenerateContentConstrained"):
    result = {'query_encoding':'percent' if encoded else 'sdk_literal','api_version':version,'method':method,'audio_sent':False}
    request=urllib.request.Request('https://app.ontrackegy.com/api/live_session.php',data=b'{"mode":"demo"}',headers={'Content-Type':'application/json'})
    with urllib.request.urlopen(request,timeout=15) as response:
        result['session_http']=response.status
        info=json.loads(response.read(250000))
    token=info.get('token','')
    result['ephemeral_token_received']=token.startswith('auth_tokens/')
    result['model']=info.get('model')
    if not result['ephemeral_token_received']:
        return result
    value=urllib.parse.quote(token,safe='') if encoded else token
    host='generativelanguage.googleapis.com'
    path='/ws/google.ai.generativelanguage.'+version+'.GenerativeService.'+method+'?access_token='+value
    with socket.create_connection((host,443),timeout=10) as tcp:
        with ssl.create_default_context().wrap_socket(tcp,server_hostname=host) as stream:
            stream.settimeout(15)
            key=base64.b64encode(os.urandom(16)).decode()
            stream.sendall(('GET '+path+' HTTP/1.1\r\nHost: '+host+'\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: '+key+'\r\nSec-WebSocket-Version: 13\r\n\r\n').encode())
            header=b''
            while b'\r\n\r\n' not in header and len(header)<16384:
                header+=read_exact(stream,1)
            lines=header.decode('iso-8859-1').split('\r\n')
            result['websocket_http_status']=int(lines[0].split(' ')[1])
            if result['websocket_http_status']!=101:
                return result
            headers={x.split(':',1)[0].lower():x.split(':',1)[1].strip() for x in lines[1:] if ':' in x}
            expected=base64.b64encode(hashlib.sha1((key+'258EAFA5-E914-47DA-95CA-C5AB0DC85B11').encode()).digest()).decode()
            if headers.get('sec-websocket-accept')!=expected:
                raise ValueError('Invalid server handshake')
            setup={'model':'models/'+info['model'].removeprefix('models/'),'generationConfig':{'responseModalities':['AUDIO'],'speechConfig':{'voiceConfig':{'prebuiltVoiceConfig':{'voiceName':'Aoede'}}}},'systemInstruction':{'parts':[{'text':info['system_instruction']}]},'inputAudioTranscription':{},'outputAudioTranscription':{}}
            send(stream,json.dumps({'setup':setup}).encode())
            fragments=b''
            for _ in range(8):
                first,second=read_exact(stream,2)
                length=second&127
                if length==126:length=struct.unpack('!H',read_exact(stream,2))[0]
                elif length==127:length=struct.unpack('!Q',read_exact(stream,8))[0]
                if length>1000000:raise ValueError('Oversized server frame')
                mask=read_exact(stream,4) if second&128 else None
                payload=read_exact(stream,length)
                if mask:payload=bytes(v^mask[i%4] for i,v in enumerate(payload))
                opcode=first&15
                if opcode==9:send(stream,payload,10);continue
                if opcode==8:
                    result['close_code']=struct.unpack('!H',payload[:2])[0] if len(payload)>=2 else None
                    result['close_reason']=payload[2:].decode('utf-8','replace')[:500].replace(token,'[REDACTED]')
                    return result
                fragments+=payload
                if not first&128:continue
                reply=json.loads(fragments);fragments=b''
                if 'setupComplete' in reply:
                    result['setup_ack']=True;send(stream,struct.pack('!H',1000),8);return result
                if 'error' in reply:
                    result['error_code']=reply['error'].get('code')
                    result['error_message']=str(reply['error'].get('message',''))[:500].replace(token,'[REDACTED]')
                    return result
    return result


if __name__=='__main__':
    results=[]
    for encoded in [True]:
        try:result=attempt(encoded)
        except Exception as error:result={'error_type':type(error).__name__,'audio_sent':False}
        results.append(result)
        if result.get('setup_ack') or result.get('close_code')==1008:break
    report={'checks':results,'cellular_audio_tested':False,'setup_pass':any(x.get('setup_ack') for x in results)}
    Path('mobile-live-check.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
    print(json.dumps(report,ensure_ascii=False))
    raise SystemExit(0 if report['setup_pass'] else 1)
