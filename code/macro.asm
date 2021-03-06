
;    PMDEmu - AVR based emulator of Czechoslovak microcomputer PMD-85 originally based on I8080
;    Copyright (C) 2004  Peter Chrenko <peto@kmit.sk>, J.Matusku 2178/21, 955 01 Topolcany, Slovakia

;    macro.asm

; A0-7  = PORTD
; A8-15 = PORTB

.equ	ADDRL	=	PORTD
.equ	ADDRH	=	PORTB
.equ	ADDRLDD = 	DDRD
.equ	ADDRHDD = 	DDRB


; D0-D7 = PORTC(OUTPUT):PINC(INPUT)
.equ	DATAIN	=	PINC
.equ	DATAOUT	=	PORTC
.equ	DATADIR = 	DDRC


; PORT E
 
	; PE0 = KBD_DATA(RXD)(IN)
	; PE1 = VIDEO_DATA (OUT)
	; PE2 = KBD_CLK(XCK) (IN)
	; PE3 = N/A
	; PE4 = N/A
	; PE5 = VIDEO_SYNC (OC3C)(OUT)
	; PE6 = N/A
	; PE7 = VIDEO_BRIGHT (OUT)

.equ	VIDEOPORT	= PORTE
.equ	VIDEOPORTDD	= DDRE
.equ	VIDEOPORTDD_value = (1<<1)|(1<<3)|(1<<7)

.macro	VIDEO_SYNC_0
	cbi	DDRE,DDE5
.endmacro	

.macro	VIDEO_SYNC_1
	sbi	DDRE,DDE5
.endmacro	


; PORT A
	; PA7 = /RD
	; PA6 = /WR

	; PA3 = SPEAKER

.equ	MEMRD	=	PA7
.equ	MEMWR	=	PA6
.equ	SPEAKER_BIT =   PA3

.equ	CONTROLRAM   = 	PORTA
.equ	SPEAKERPORT  =  PORTA
.equ	CONTROLRAMDD = 	DDRA

.equ	CONTROLRAMDD_value = (1<<MEMWR)|(1<<MEMRD)|(1<<SPEAKER_BIT)

;flags
.equ	PMD_CY	= 0
.equ	PMD_P	= 2
.equ	PMD_AC	= 4
.equ	PMD_Z	= 6
.equ	PMD_S	= 7
.equ	PMD_PSW	= 0b00000010	; empty PSW

.equ	ATMEL_C		= 0
.equ	ATMEL_Z		= 1
.equ	ATMEL_N		= 2
.equ	ATMEL_V		= 3
.equ	ATMEL_S		= 4
.equ	ATMEL_H		= 5
.equ	ATMEL_T		= 6
.equ	ATMEL_I		= 7

.macro	SET_SPEAKER_1
	sbi	SPEAKERPORT,SPEAKER_BIT
.endmacro

.macro	SET_SPEAKER_0
	cbi	SPEAKERPORT,SPEAKER_BIT
.endmacro

.macro	MEMRD_active
		cbi			CONTROLRAM,MEMRD
.endmacro


.macro	MEMRD_deactive
		sbi			CONTROLRAM,MEMRD
.endmacro

.macro	MEMWR_active
		cbi			CONTROLRAM,MEMWR
.endmacro


.macro	MEMWR_deactive
		sbi			CONTROLRAM,MEMWR
.endmacro


; kb_lookup
	 	.equ	r0 = 1  ; definition of rows of PMD keyboard
		.equ	r1 = 2  ; 2nd row
		.equ	r2 = 4  ; 3rd row
		.equ	r3 = 8  ; 4th row
		.equ	r4 = 15 ; 5th row
		.equ	rx = 0  ; mark - not used key

