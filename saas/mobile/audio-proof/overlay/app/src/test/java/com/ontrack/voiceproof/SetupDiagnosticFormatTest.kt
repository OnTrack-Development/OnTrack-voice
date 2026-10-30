package com.ontrack.voiceproof

import org.junit.Assert.*
import org.junit.Test

class SetupDiagnosticFormatTest {
    @Test fun usesActualProfileAndPreservesExitCode() {
        assertEquals("pm grant --user 10 com.ontrack.voiceproof android.permission.WRITE_SECURE_SETTINGS 2>&1; echo pm-exit=$?",
            SetupDiagnosticFormat.grant("com.ontrack.voiceproof", 10))
    }
    @Test fun permissionReadBackIsNarrow() {
        val command = SetupDiagnosticFormat.readBack("com.ontrack.voiceproof", 0)
        assertTrue(command.contains("User 0:"))
        assertTrue(command.contains("WRITE_SECURE_SETTINGS"))
        assertFalse(command.contains("cat "))
    }
    @Test fun rejectsInjectedShellText() {
        for (pkg in listOf("com.example;id", "com.example'", "com.example\nreboot", "com.example x")) {
            assertThrows(IllegalArgumentException::class.java) { SetupDiagnosticFormat.grant(pkg, 0) }
        }
    }
    @Test fun rejectsInvalidUser() {
        assertThrows(IllegalArgumentException::class.java) { SetupDiagnosticFormat.grant("com.ontrack.voiceproof", -1) }
    }
}
