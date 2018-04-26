 $( document ).ready(function() {
       var urls=[];
       var word="";
       var success_count=0;
       var fail_count=0;
      
      function reset_all()
      {
         $("#failed").text("0");
          $("#incorrect").text("0");
          $("#correct").text("0");
          $("#completed").html("");
          $("#failed").html("");
          $("#pages").hide();
          $("#preloader").hide();
          $(".message").text("");
          $(".validation").text("");
        
      }
      

      function get_ajax(index)
      {        
        $("#pages").show();
        $("#preloader").css("display","inline-block");
        
        if(index<urls.length){
          $('button[type="submit"]').prop('disabled', true);
         $.ajax({
                 type: "POST",
                 url: "index.php",
                 data: "url="+encodeURIComponent(urls[index])+"&word="+word,
                 success: function(msg){
                   var data=JSON.parse(msg);
                   if(data.status==1)
                   {
                     $("#completed").append('<li><a href="'+data.url+'">'+data.url+'</a></li>');
                     var current_complete=parseInt($("#correct").text())+1;
                     $("#correct").text(current_complete);

                   }else if(data.status==2)
                   {
                     var current_complete=parseInt($("#correct").text())+1;
                     $("#correct").text(current_complete);    
                   }
                   else if(data.status==0)
                   {
                      $("#failed").append('<li><a href="'+data.url+'">'+data.url+'</a></li>');
                     var current_failed=parseInt($("#incorrect").text())+1;
                     $("#incorrect").text(current_failed);
                   }
                  

                   get_ajax(index+1); 
    
                },fail:function(){
                  $("#failed").append('<li><a href="'+urls[index]+'">'+urls[index]+'</a></li>');
                  var current_failed=parseInt($("#incorrect").text())+1;
                     $("#incorrect").text(current_failed);
                   get_ajax(index+1);
                }
             });
         }else
         {
          $('button[type="submit"]').prop('disabled', false);
          $("#preloader").hide();
         }
      }
      
      var url="";     
     
      
      $("form").on("submit",function(e){        
         e.preventDefault();
         word=$('input[name="url"]').val();
         if(word.length>0)
         {
             reset_all();
            $('button[type="submit"]').prop('disabled', true);

             $.ajax({
                 type: "POST",
                 url: "index.php",
                 data: "csv=1",
                 success: function(msg){
                     var data=JSON.parse(msg);
                     urls=data;
                     if(urls.length>0)
                     {
                         $("#total").text(urls.length);
                         get_ajax(0);
                     }

                     else
                     {
                         $(".message").text("Websites list is empty.");
                     }
                 },fail:function(){

                     $(".message").text("Error. Request not send..");
                     $('button[type="submit"]').prop('disabled', false);
                 }
             });
         }else
         {
             $(".validation").text("Field must be not empty!")
         }

      });
      
   
});