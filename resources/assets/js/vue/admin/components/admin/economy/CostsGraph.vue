<template>
    <div>
        <i v-show="loading" class="fa fa-spinner fa-spin loader"></i>
        <h2 v-show="!loading" class="text-center">Costs 2017 - Total {{ total }} SEK</h2>
        <div v-show="!loading" id="costs-chart" class="chart" style="height: 300px;"></div>
    </div>
</template>

<script>
    export default {
        data: function() {
            return {
                loading: true,
                incomeTransactions: null,
                total: null,
            }
        },
        mounted() {
            axios.get('/admin/api-proxy', {
                params: {
                    url: '/api/v1/economy/transactions',
                }
            })
            .then(response => {
                let data = this.formatData(response.data.transactions, response.data.categories);
                this.draw(data);
                this.loading = false;
            });
        },
        methods: {
            formatData(transactions, categories) {
                let filteredTransactions = _.filter(transactions, transaction => {
                    return transaction.amount < 0;
                })

                let costs = _(filteredTransactions).groupBy(transaction => {
                    return transaction.category;
                }).map(function(category, categoryId) {
                    let sum = _.sumBy(category, transactions => {
                        return transactions.amount;
                    });

                    let categoryObject = _.find(categories, category => {
                        return category.id == categoryId;
                    });

                    let sumString = '\r' + sum + ' SEK';
                    let label = (categoryObject && categoryObject.label) ? categoryObject.label : 'Uncategorized';
                    label += sumString;

                    return [label, -sum]; // Convert to positive
                })
                .value();

                let total = _.sumBy(filteredTransactions, transaction => {
                    return transaction.amount;
                });

                this.total = -total;

                // Add headers
                costs.unshift(['Category', 'Amount']);

                return costs;
            },
            draw(dataArray) {
                google.charts.load("current", {packages:["corechart"]});
                google.charts.setOnLoadCallback(drawChart);

                function drawChart() {
                    var dataTable = google.visualization.arrayToDataTable(dataArray);

                    var options = {
                        chartArea: {
                            top: 20,
                            left: 20,
                            width: '90%',
                            height: '90%',
                        },
                        pieHole: 0.4,
                        tooltip: { trigger: 'selection' },
                        legend: {
                            alignment: 'center',
                        }
                    };

                    var chart = new google.visualization.PieChart(document.getElementById('costs-chart'));
                    chart.draw(dataTable, options);
                }
            }
        }
    }
</script>
