(function($){
 $(document).ready(function(){
     
  let _cat = jQuery('#categories').val();
   
  if(_cat) {
      
        let categories = JSON.parse(jQuery('#categories').val());
        let data1 = JSON.parse(jQuery('#data1').val());
        let data_avg = JSON.parse(jQuery('#data_avg').val());

        var myChart = Highcharts.chart('hc-container', {
                  chart: {
                      type: 'bar'
                  },
                  title: {
                      text: null,
                  },
                  credits: null,
                  xAxis: {
                      categories: categories
                  },
                  yAxis: {
                      title: {
                          text: '%'
                      },
                      max: 100
                  },
                  plotOptions: {
                    series: {
                        pointWidth: 15,
                        cursor: 'pointer',
                        point: {
                            events: {
                                click: function () {
                                    let marker = 'sub-' + this.category.split(' - ')[0];
                                    let toelement = document.getElementById(marker);
                                    toelement.scrollIntoView({
                                        behavior: 'smooth'
                                    });
                                }
                            }
                        },
                        tooltip: {
                            valueSuffix: ' %'
                        }
                      }
                  },
                  series: [{
                      name: 'Performance',
                      data: data1.map(x => Math.round(x * 100)),
                      color: '#0f2166'
                  }, {
                      name: 'Average data',
                      data: data_avg.map(x => Math.round(x * 100)),
                      color: '#1d7f98'
                  }]
            });
    }
 });
 
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
    });
});

})(jQuery);