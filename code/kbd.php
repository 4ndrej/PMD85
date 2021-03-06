;    
;    PMDEmu - AVR based emulator of Czechoslovak microcomputer PMD-85 originally based on I8080
;    Copyright (C) 2003  Peter Chrenko <peto@kmit.sk>, J.Matusku 2178/21, 955 01 Topolcany, Slovakia
;
;    This program is free software; you can redistribute it and/or modify
;    it under the terms of the GNU General Public License as published by
;    the Free Software Foundation; either version 2 of the License, or
;    (at your option) any later version.
;
;    This program is distributed in the hope that it will be useful,
;    but WITHOUT ANY WARRANTY; without even the implied warranty of
;    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
;    GNU General Public License for more details.
;
;    You should have received a copy of the GNU General Public License
;    along with this program; if not, write to the Free Software
;    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
;

; KBD.PHP ; included from video.php
; test every microline (each 64 us), if keystroke

.macro STORE_KBD_REGS
		; SREG is saved in video_SREG register
		mov		video_CONTROLRAM,kbd_reg
		movw		video_ADDRL,ZL		; save Z

.endmacro

.macro RESTORE_KBD_REGS
		movw		ZL,video_ADDRL		; restore Z
		mov		kbd_reg,video_CONTROLRAM
		out		SREG,video_SREG		; must be
.endmacro

;################################################################
; routine executed if USART received some char 
		
		; SREG is saved in video_SREG register
		
		STORE_KBD_REGS

		in	video_tmp,UCSR0A
		in	kbd_reg,UDR0			; kb_data from USART
		
		andi	video_tmp, (1<<FE0)|(1<<UPE0)|(1<<DOR0)
		brne	kb_end				; USART receiver error detected

		cpi		kbd_reg,0xf0		; if 0xF0 received we know key is released, othervise is pressed.
		breq		f0_handle

		; kbd_reg = scan code (PC keyboard)
		; now we compute column and row of pressed/released key from its scan 
		bst		kbd_flags,F0_bit	; T := 0 if pressed, 1 if released 
		ldi		video_tmp,~(1<< F0_bit)
		and		kbd_flags,video_tmp	; clear it
		
		cpi		kbd_reg,0x11        	; ALT ?
		breq		alt_handle

		sbrc		kbd_flags,ALT_bit
		rjmp		alt_isnt_pressed
		brts		kb_end			; return if released
		
		; in kbd_reg is scan code (PC keyboard)
		cpi		kbd_reg,0x16		; ALT+1 ===> RESET PMD-85
		breq		reboot_pmd
		cpi		kbd_reg,0x76		; ALT+ESC ===> RESET PMD-85
		breq		reboot_pmd
		cpi		kbd_reg,0x1b		; ALT+S ===> dynamic stop
		breq		alt_s_handle
		cpi		kbd_reg,0x21		; ALT+C ===> color mode switch
		breq		alt_c_handle		
		rjmp		kb_end

alt_isnt_pressed:	

		cpi		kbd_reg,0x12		; left shift ?
		breq		shift_handle
		cpi		kbd_reg,0x59		; right shift ?	
		breq		shift_handle
		cpi		kbd_reg,0x84
		brsh		kb_end			; not in look-up table --> ignore key
		cpi		kbd_reg,0x14		; CTRL ?
		breq		stop_handle
		
		; kbd_reg = SCAN code of pressed (T=0)/released(T=1) key
		; T <=>  0 == pressed, 1 == released
		
		

		ldi		ZH, high(kb_lookup << 1)
		ldi		ZL, low(kb_lookup << 1)
		add		ZL, kbd_reg 
		adc		ZH, _zero
		
		; Z := kb_lookup + 1*SCAN_CODE
		lpm		video_tmp,Z
		ldi		ZL, low ( kb_cols )
		ldi		ZH, high ( kb_cols )
		
		
		cpi		kbd_reg,0x5A        ; second EOL (ENTER) handle
		brne	_no_enter_hit	
				
		; ative/deactive also EOL on r4:13
		ldd		kbd_reg, Z + 13 	; load 13th column 
		bld		kbd_reg,4 		; copy bit , EOL2 (4.bit)
		std		Z + 13,kbd_reg  	; store 13th column	
		


_no_enter_hit:
		mov	kbd_reg,video_tmp
		andi	video_tmp,0x0f 			; row mask: 0=invalid, 1,2,4,8 and 15 (must ---> 16)   - valid
		breq	kb_end  			; 0 ==  not used key 
		
		swap	kbd_reg
		andi	kbd_reg,0x0f
		
		; now:
		; video_tmp = part of row mask
		; kbd_reg = column
		
		
		cpi	video_tmp,14  ; 5th row? clear carry  if video_tmp == 15, set carry if video_tmp < 15
		sbci	video_tmp,-1  ; calculate with carry ;) 
		
		add		ZL, kbd_reg      ; Z := kb_cols + kbd_reg (column address)

		ld		kbd_reg,Z
		
		or		kbd_reg,video_tmp
		brts		kb_write_one
		com		video_tmp
		and		kbd_reg,video_tmp
kb_write_one:	st		Z,kbd_reg
		

kb_end:
		RESTORE_KBD_REGS
		reti		

f0_handle:
		set
		bld		kbd_flags, F0_bit 		; store it (released/pressed bit)
		rjmp		kb_end	

shift_handle:
		bld		kbd_flags,SHIFT_bit		; store SHIFT bit (5. bit)
		rjmp		kb_end		

alt_handle:
		bld		kbd_flags,ALT_bit	; store ALT bit, ALT := T	
		rjmp		kb_end

stop_handle:
		bld		kbd_flags,6	     	; copy to STOP bit
		rjmp		kb_end	     		; end			

reboot_pmd:	
		rjmp		PMD_RESET



alt_s_handle:
		brts		kb_end			
		lds		kbd_reg,stop_flag
		com		kbd_reg
		sts		stop_flag,kbd_reg
		
		breq		kb_end		; if stop_flag is zero
		
		RESTORE_KBD_REGS		; if stop_flag is non zero ===> dynamic stop
		sei
		
		rcall		video_osd	; print intro text	
		;rcall		video_scrollup
		reti

alt_c_handle:	; switch colors scheme
		RESTORE_KBD_REGS		 
		sei
		push		ZL
		push		ZH
		push		YL
		push		YH
		in		ZL,SREG
		push		ZL

		ldi		ZH,high(blink_lookup_table)
		ldi		ZL,low(blink_lookup_table)	; zero
		ldi		YH,0x80				; invert 7th bit 
switch_attributtes:
		ld		YL,Z
		eor		YL,YH
		st		Z+,YL
		cpi		ZH,high(blink_lookup_table+0x200)
		brne		switch_attributtes	

		
		pop		ZL
		out		SREG,ZL
		pop		YH
		pop		YL
		pop		ZH
		pop		ZL

		ret

	
<?

$strings = array( "",
                  "PMD-85-1 EMULATOR",
		  "=================",
                  "",
		  "PETER CHRENKO",
		  "J.Matusku 2178/21",
		  "955 01 TOPOLCANY",
		  "peto@kmit.sk",
		  "", 
		  "BUILT: ".Date("j.n.Y G:i"),
                  ""
		 );

$dy = count($strings);

$dx = 0;

foreach( $strings as $s )
{
  $dx = max( $dx, 2+strlen($s) );
}

$dx = 2*ceil($dx/2.);  

$tmp = array();

foreach( $strings as $s )
{
  $tmp[] = strtoupper(str_pad(' '.$s, $dx));
}

$rv = '';
for($x = 0; $x < $dx; $x++ )
{
   for($y = 0; $y < $dy; $y++ )
   { 
        $rv .= substr($tmp[$y],$x,1);
   }
}


echo "text_info:\t.db\t\"$rv\"\n\n";


$osd_begin_line = 0xc000+(25-$dy)/2*64*10;
$osd_begin2 = $osd_begin_line + (48-$dx)/2;  
$osd_end_line   = $osd_begin2 + 64*$dy*10-64 + ($dx-1);


$osd_begin	= sprintf("0x%04X", $osd_begin2 ); // center 

?>	

	; OSD routines

video_osd:
	.def	ptrl	=	r22
	.def	ptrh	=	r23
	.equ	chr_space = 0x40
	

	push	ptrl
	push	ptrh
	push	YL
	push	YH
	push	ZL
	push	ZH
	push	r24
	push	r25
	
	push	kbd_portC		; disable sound
	mov	kbd_portC,_zero
	
	in ZL,CONTROLRAM
	push	ZL
	in ZL,ADDRL	
	push	ZL
	in ZL,ADDRH
	push	ZL
	in ZL,DATADIR	
	push	ZL
	in ZL,DATAOUT
	push	ZL
	in	ZL,SREG
	push	ZL

osd_read:
	
	ldi	ptrl,low( <? echo $osd_begin ?>  )	
	ldi	ptrh,high ( <? echo $osd_begin ?> )
	
	out	DATADIR,_zero		; input for read operation
	MEMRD_active

	ldi	YH, <? echo $dy*10; ?>
	
osd_read_y:
	out	ADDRH,ptrh
	ldi	YL, <? echo $dx; ?>

osd_read_x:	
	out	ADDRL,ptrl
	inc	ptrl
	dec	YL
	<? MDELAY(2); ?>
	in	ZL,DATAIN
	push	ZL
	
	brne	osd_read_x
	
	subi	ptrl,low(<? echo -(64-$dx);  ?>)
	sbci	ptrh,high(<? echo -(64-$dx);  ?>)

        dec	YH
	brne	osd_read_y
	
	; here we print text	
	ldi	ptrl,low( <? echo $osd_begin; ?>  )	
	ldi	ptrh,high ( <? echo $osd_begin; ?> )
	ldi	ZL,low ( text_info * 2 )
	ldi	ZH,high( text_info * 2 )

osd_print_char_loop:
	lpm	YL,Z+

	;compute char address
	clr	YH
	lsl	YL		; char is always with 7th bit cleared
	lsl	YL
	rol	YH
	lsl	YL
	rol	YH		; Y *= 8
	subi	YH,-133		; Y += 0x8500
	
	ldi	r25,9		; 9+1=10 microlines
	ldi	r24, chr_space
	rjmp	osd_blank
osd_one_char:
	out	ADDRH,YH
	out	ADDRL,YL
	adiw	YL,1
	<? MDELAY(2); ?>
	in	r24,DATAIN
	ori	r24,0x40	; add bright attribute
osd_blank:
	<? _write('ptrh','ptrl', 'r24', true, true, true,64,false ); ?>
	dec	r25
	brne	osd_one_char
	ldi	r24, chr_space	; 10th microline
	<? _write('ptrh','ptrl', 'r24', true, true, true,64,false ); ?>
	
	cpi	ptrh,high(<? echo "$osd_begin + $dy*64*10"; ?>)
	brlo	osd_print_char_loop

	subi	ptrl, low(<? echo 64*10*$dy-1; ?>)	
	sbci	ptrh, high(<? echo 64*10*$dy-1; ?>)	
	
	cpi	ptrl, low(<? echo "$osd_begin + $dx"; ?>)
	brne	osd_print_char_loop
	

	

dynamic_stop:
		lds		ZL,stop_flag
		cpse		ZL,_zero	
		rjmp		dynamic_stop



osd_write:
	ldi	ptrl,low( <? echo $osd_end_line; ?> )	
	ldi	ptrh,high ( <? echo $osd_end_line; ?> )

	ldi	YH, <? echo $dy*10; ?>
	
osd_write_y:	
	ldi	YL, <? echo $dx; ?>

osd_write_x:
	pop	ZL
	<? 
	$GLOBALS['write_add_special'] = true;
	_write('ptrh','ptrl', 'ZL', true, true, false,-1,false ); ?>
	
	dec	YL
	brne	osd_write_x	

	subi	ptrl,low(<? echo 64-$dx;  ?>)
	sbci	ptrh,high(<? echo 64-$dx;  ?>)

	dec	YH
	brne	osd_write_y	

	pop	ZL
	out	SREG,ZL
	pop	ZL
	out	DATAOUT,ZL
	pop	ZL
	out	DATADIR,ZL
	pop	ZL
	out	ADDRH,ZL
	pop	ZL
	out	ADDRL,ZL
	pop	ZL
	out	CONTROLRAM,ZL

	pop	kbd_portC
	
	pop	r25
	pop	r24
	pop	ZH
	pop	ZL
	pop	YH
	pop	YL
	pop	ptrh
	pop	ptrl
	ret


<? /*

video_scrollup:

	push	ptrl
	push	ptrh
	push	YL
	push	YH
	push	ZL
	push	ZH
	push	r24
	push	r25
	
	push	kbd_portC		; disable sound
	mov	kbd_portC,_zero
	
	in ZL,CONTROLRAM
	push	ZL
	in ZL,ADDRL	
	push	ZL
	in ZL,ADDRH
	push	ZL
	in ZL,DATADIR	
	push	ZL
	in ZL,DATAOUT
	push	ZL
	in	ZL,SREG
	push	ZL


	ldi	ptrl,low( 0xc000 + 64)	
	ldi	ptrh,high(0xc000 + 64)

	ldi	YL,low ( video_save )
	ldi	YH,high( video_save )


__up:
	out	DATADIR,_zero		; input for read operation
	MEMRD_active
	out	ADDRH,ptrh

ccc1:	
	out	ADDRL,ptrl
	inc	ptrl
	<? MDELAY(1); ?>
	in	r24,DATAIN
	st	Y+,r24
	cpi	YL, low(video_save+48)	
	brlo	ccc1
	subi	ptrl,low(64+64-16)
	sbci	ptrh,high(64+64-16)            ; skip it
	ori	ptrh, 0xc0
	
	ldi	YL,low ( video_save )
	MEMRD_deactive
	out	DATADIR,_255
	
ccc2:
	ld	r24,Y+

	<? $GLOBALS['write_add_special'] = true;
	   _write('ptrh','ptrl', 'r24', true, false, false,1,false ); ?>
	cpi	YL, low(video_save+48)	
	brlo	ccc2

	subi	ptrl,low(-(16+64))
	sbci	ptrh,high(-(16+64))            ; skip it
	ori	ptrh, 0xc0
	
	
	rjmp	__up
*/ ?>


