import java.nio.file.*;
import java.security.*;
import java.security.cert.CertificateFactory;
import java.security.cert.X509Certificate;
import java.io.ByteArrayInputStream;

/** Validates the existing SHA256withRSA mobile OTA manifest on JDK21 Windows. */
public class VerifySignedMobileOta {
    public static void main(String[] args) throws Exception {
        if (args.length != 3) throw new IllegalArgumentException("cert.der payload signature required");
        byte[] certificateBytes = Files.readAllBytes(Path.of(args[0]));
        X509Certificate cert = (X509Certificate) CertificateFactory.getInstance("X.509")
            .generateCertificate(new ByteArrayInputStream(certificateBytes));
        Signature verifier = Signature.getInstance("SHA256withRSA");
        verifier.initVerify(cert.getPublicKey());
        verifier.update(Files.readAllBytes(Path.of(args[1])));
        if (!verifier.verify(Files.readAllBytes(Path.of(args[2])))) {
            throw new SecurityException("OTA manifest signature invalid");
        }
        System.out.println("SIGNED_OTA_RSA_SIGNATURE_PASS");
    }
}
