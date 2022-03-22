// Link to Circuit Diagram https://drive.google.com/file/d/1lrTDolI1vuRx57GEYWbj-m9eXdcu_grJ/view?usp=sharing
// This .php code is used for experimental purposes.
It is designed to experiment with flow colorimetry.
Put togeter by Conscious Energy, the purpose of its use is to explore table top Fusion Experiments.
Conscioius Energies holds no responsibility for the open source use of this code. 
Item list- 
Two or more DS18B20 one wire Digital temprature sensors 
One PZEM004T Enegy Sensor 
One MAX6675 PCB Module and 1 K-Type Thermocouple 
and ofcourse, a reactor design of your choosing.

#include <OneWire.h>

#include <SoftwareSerial.h> // Arduino IDE <1.6.6

#include <PZEM004T.h>

PZEM004T pzem(12,11);  // (RX,TX) connect to TX,RX of PZEM
IPAddress ip(192,168,1,1);

// This Arduino sketch reads DS18B20 "1-Wire" digital
// Attach as many DS18B20 Digital temperature sensors as you need.
// Tutorial:
// http://www.hacktronics.com/Tutorials/arduino-1-wire-tutorial.html

#include <OneWire.h>
#include <DallasTemperature.h>

// Data wire is plugged into pin 3 on the Arduino
#define ONE_WIRE_BUS 3

// Setup a oneWire instance to communicate with any OneWire devices
OneWire oneWire(ONE_WIRE_BUS);

// Pass our oneWire reference to Dallas Temperature. 
DallasTemperature sensors(&oneWire);

// Assign the addresses of your 1-Wire temp sensors.
// See the tutorial on how to obtain these addresses:
// http://www.hacktronics.com/Tutorials/arduino-1-wire-address-finder.html

DeviceAddress insideThermometer = { 0x28, 0xFF, 0xCC, 0xC5, 0x64, 0x15, 0x01, 0x85 };
DeviceAddress outsideThermometer = { 0x28, 0xFF, 0x33, 0x16, 0x84, 0x16, 0x04, 0x68 };

// Max6675 K Type Thermocoulple pcb module 

#include "max6675.h"

int thermoSO = 8;
int thermoCS = 9;
int thermoCLK = 10;

MAX6675 thermocouple(thermoCLK, thermoCS, thermoSO);
int vccPin = 0;
int gndPin = 0;


//Flow meter for Flow Calorimetry
/*

Measure the liquid/water flow rate using this code. 
Connect Vcc and Gnd of sensor to arduino, and the 
signal line to arduino digital pin 2.
 
 */

byte statusLed    = 13;

byte sensorInterrupt = 0;  // 0 = digital pin 2
byte sensorPin       = 2;

// The hall-effect flow sensor outputs approximately 4.5 pulses per second per
// litre/minute of flow.
float calibrationFactor = 4.5;

volatile byte pulseCount;  

float flowRate;
unsigned int flowMilliLitres;
unsigned long totalMilliLitres;

unsigned long oldTime;

void setup(void)
{
  // start serial port
  Serial.begin(115200);
   pzem.setAddress(ip);

  // Start up the library
  sensors.begin();
  // set the resolution to 10 bit (good enough?)
  sensors.setResolution(insideThermometer, 10);
 
  sensors.setResolution(outsideThermometer,10);

  // use Arduino pins 
  pinMode(vccPin, OUTPUT); digitalWrite(vccPin, HIGH);
  pinMode(gndPin, OUTPUT); digitalWrite(gndPin, LOW);
  
 
  // wait for MAX chip to stabilize
  delay(500);
}

void printTemperature(DeviceAddress deviceAddress)
{
  float tempC = sensors.getTempC(deviceAddress);
  if (tempC == -127.00) {
    Serial.print("Error getting temperature");
  } else {
    
    Serial.print("");
    Serial.print(tempC);
    Serial.print(",");
    Serial.print(DallasTemperature::toFahrenheit(tempC));
    
  }
{
  
  // Initialize a serial connection for reporting values to the host
    
   
  // Set up the status LED line as an output
  pinMode(statusLed, OUTPUT);
  digitalWrite(statusLed, HIGH);  // We have an active-low LED attached
  
  pinMode(sensorPin, INPUT);
  digitalWrite(sensorPin, HIGH);

  pulseCount        = 0;
  flowRate          = 0.0;
  flowMilliLitres   = 0;
  totalMilliLitres  = 0;
  oldTime           = 0;

  // The Hall-effect sensor is connected to pin 2 which uses interrupt 0.
  // Configured to trigger on a FALLING state change (transition from HIGH
  // state to LOW state)
  attachInterrupt(sensorInterrupt, pulseCounter, FALLING);
}
}

void loop(void)
 {

//PZEM Power Monitor
float v = pzem.voltage(ip);
  if (v < 0.0) v = 0.0;
  Serial.print(v);Serial.print(",");
//volts
  float i = pzem.current(ip);
  if(i >= 0.0){ Serial.print(i);Serial.print(","); }
  //amps
  float p = pzem.power(ip);
  if(p >= 0.0){ Serial.print(p);Serial.print(","); }
  //watts
  float e = pzem.energy(ip);
  if(e >= 0.0){ Serial.print(e);Serial.print(","); }
//watthours


  sensors.requestTemperatures();
  
  printTemperature(insideThermometer);
 
    Serial.print(",");


  printTemperature(outsideThermometer);
  Serial.print("");
    Serial.print(""); 
 
 

  // basic readout test, just print the current temp
 
   Serial.print(","); 
   Serial.print(thermocouple.readCelsius());
   Serial.print(",");
   Serial.print(thermocouple.readFahrenheit()); 
   delay(500);



   
   if((millis() - oldTime) > 1000)    // Only process counters once per second
  { 
    // Disable the interrupt while calculating flow rate and sending the value to
    // the host
    detachInterrupt(sensorInterrupt);
    Serial.print("");
        
    // Because this loop may not complete in exactly 1 second intervals we calculate
    // the number of milliseconds that have passed since the last execution and use
    // that to scale the output. We also apply the calibrationFactor to scale the output
    // based on the number of pulses per second per units of measure (litres/minute in
    // this case) coming from the sensor.
    flowRate = ((1000.0 / (millis() - oldTime)) * pulseCount) / calibrationFactor;
    
    // Note the time this processing pass was executed. Note that because we've
    // disabled interrupts the millis() function won't actually be incrementing right
    // at this point, but it will still return the value it was set to just before
    // interrupts went away.
    oldTime = millis();
    
    // Divide the flow rate in litres/minute by 60 to determine how many litres have
    // passed through the sensor in this 1 second interval, then multiply by 1000 to
    // convert to millilitres.
    flowMilliLitres = (flowRate / 60) * 1000;
    
    // Add the millilitres passed in this second to the cumulative total
    totalMilliLitres += flowMilliLitres;
      
    unsigned int frac;
    
    // Print the flow rate for this second in litres / minute
   
    Serial.print(",");
    Serial.print(int(flowRate));  // Print the integer part of the variable
    Serial.print(".");             // Print the decimal point
    // Determine the fractional part. The 10 multiplier gives us 1 decimal place.
    frac = (flowRate - int(flowRate)) * 10;
    Serial.print(frac, DEC) ;      // Print the fractional part of the variable
    Serial.print(",");
    Serial.print("");
    // Print the number of litres flowed in this second
    Serial.print("");             // Output separator
    Serial.print(flowMilliLitres);
    Serial.print(",");
    Serial.print("");
    // Print the cumulative total of litres flowed since starting
    Serial.print("");             // Output separator
    Serial.print(totalMilliLitres);
    Serial.print(","); 
    Serial.print("\t");
    Serial.println("");
    // Reset the pulse counter so we can start incrementing again
    pulseCount = 0;
    
    // Enable the interrupt again now that we've finished sending output
    attachInterrupt(sensorInterrupt, pulseCounter, FALLING);
  }
}

/*
Insterrupt Service Routine
 */
void pulseCounter()
{
  // Increment the pulse counter
  pulseCount++;
}
