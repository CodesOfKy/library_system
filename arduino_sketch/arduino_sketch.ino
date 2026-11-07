#include <SPI.h>
#include <MFRC522.h>

#define SS_PIN 10
#define RST_PIN 9

#define LED_PIN 4
#define BUZZER_PIN 3

MFRC522 rfid(SS_PIN, RST_PIN);

void setup() {
  Serial.begin(9600);
  SPI.begin();
  rfid.PCD_Init();

  pinMode(LED_PIN, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);

  digitalWrite(LED_PIN, LOW);
}

void loop() {
  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) {
    return;
  }

  String uid = "";

  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10) {
      uid += "0";
    }

    uid += String(rfid.uid.uidByte[i], HEX);
  }

  uid.toUpperCase();

  // Print scanned card UID
  Serial.println(uid);

  // LED ON
  digitalWrite(LED_PIN, HIGH);

  // Passive buzzer beep
  tone(BUZZER_PIN, 1000);
  delay(200);
  noTone(BUZZER_PIN);

  delay(300);

  // LED OFF
  digitalWrite(LED_PIN, LOW);

  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();

  delay(1000);
}