package com.ontrack.vs;

/** Standalone JVM checks for the OnTrack LinkPcm parity contract. */
public final class CallPcmParityTest {
    private static void check(boolean valid, String label) {
        if (!valid) throw new AssertionError(label);
    }
    private static int sample(byte[] pcm, int i) {
        return (short)((pcm[i] & 255) | (pcm[i+1] << 8));
    }
    private static byte[] frames(int... leftRight) {
        check(leftRight.length % 2 == 0, "pairs");
        byte[] bytes = new byte[leftRight.length * 2];
        for (int i=0; i<leftRight.length; i++) {
            bytes[2*i]=(byte)leftRight[i];
            bytes[2*i+1]=(byte)(leftRight[i]>>8);
        }
        return bytes;
    }
    public static void main(String[] args) {
        CallPcm right=new CallPcm("right");
        byte[] a=right.input(frames(100,300,100,600,100,900,100,1200));
        check(a.length==2 && sample(a,0)==600, "first 3-frame right average");
        byte[] b=right.input(frames(100,1500,100,1800));
        check(b.length==2 && sample(b,0)==1500, "cross-packet right continuity");
        CallPcm left=new CallPcm("left");
        byte[] l=left.input(frames(100,300,200,600,300,900));
        check(l.length==2 && sample(l,0)==200, "left selection");
        CallPcm mix=new CallPcm("mix");
        byte[] m=mix.input(frames(100,300,200,600,300,900));
        check(m.length==2 && sample(m,0)==400, "left/right mix");
        byte[] up=CallPcm.output(frames(1,2),16000);
        check(up.length==12 && sample(up,0)==1 && sample(up,2)==1
            && sample(up,4)==1 && sample(up,6)==2 && sample(up,10)==2,
            "16k to 48k sample duplication");
        System.out.println("VS LinkPcm parity PASS: right/left/mix, packet continuity, 16k->48k");
    }
}
