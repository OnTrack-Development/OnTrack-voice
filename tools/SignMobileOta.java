import java.nio.file.*;
import java.io.*;
import java.security.*;
import java.util.Base64;

/** Uses existing Android signing identity; never emits private key material. */
public class SignMobileOta {
    public static void main(String[] args) throws Exception {
        KeyStore store=KeyStore.getInstance(new File(args[0]),"android".toCharArray());
        PrivateKey key=(PrivateKey)store.getKey("androiddebugkey","android".toCharArray());
        java.security.cert.Certificate cert=store.getCertificate("androiddebugkey");
        String certHash=java.util.HexFormat.of().formatHex(MessageDigest.getInstance("SHA-256").digest(cert.getEncoded()));
        if(!certHash.equals("d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f"))throw new SecurityException("Unexpected signing identity; preserve existing install lineage");
        Path folder=Path.of(args[1]);String payload=Files.readString(folder.resolve("payload.json"));
        Signature signature=Signature.getInstance("SHA256withRSA");signature.initSign(key);signature.update(payload.getBytes(java.nio.charset.StandardCharsets.UTF_8));
        String quoted=payload.replace("\\","\\\\").replace("\"","\\\"");
        Files.writeString(folder.resolve("latest.json"),"{\"payload\":\""+quoted+"\",\"signature\":\""+Base64.getEncoder().encodeToString(signature.sign())+"\"}\n");
        Files.write(folder.resolve("signing-cert.der"),cert.getEncoded());
        Files.delete(folder.resolve("payload.json"));
        System.out.println("Signed OTA manifest with existing APK identity: "+certHash);
    }
}
