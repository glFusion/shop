$(document).ready(function(){
	$('.shop_datepicker').each(function(i, obj) {
		var id = $(this).attr('id');
		shop_datetimepicker_datepicker(id);
	});
	$('.shop_timepicker').each(function(i, obj) {
		var id = $(this).attr('id');
		shop_datetimepicker_timepicker(id);
	});
});
function shop_datetimepicker_datepicker( selector ) {
	var currentDT = $("#"+selector).val();
	$('#'+selector).val( currentDT );
	$('#'+selector).datetimepicker({
		lazyInit: true,
		value:currentDT,
		format:'Y-m-d',
		timepicker: false,
	});
}
function shop_datetimepicker_timepicker( selector ) {
	var currentDT = $("#"+selector).val();
	$('#'+selector).val( currentDT );
	$('#'+selector).datetimepicker({
		lazyInit: true,
		value:currentDT,
		format:'H:i',
		datepicker: false,
		step: 15,
	});
}
