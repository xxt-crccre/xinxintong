xxtApp.controller('lotteryCtrl', ['$scope', 'http2', '$location', function($scope, http2, $location) {
    $scope.awardTypes = [{
        n: '未中奖',
        v: '0'
    }, {
        n: '应用积分',
        v: '1'
    }, {
        n: '奖励重玩',
        v: '2'
    }, {
        n: '完成任务',
        v: '3'
    }, {
        n: '实体奖品',
        v: '99'
    }, ];
    $scope.lid = $location.search().lid;
    http2.get('/rest/mp/mpaccount/get', function(rsp) {
        $scope.mpaccount = rsp.data;
        http2.get('/rest/mp/app/lottery/get?lid=' + $scope.lid, function(rsp) {
            $scope.lottery = rsp.data;
            if ($scope.lottery.awards === undefined) {
                $scope.lottery.awards = [];
            }
        });
    });
}]).controller('SettingCtrl', ['$scope', '$modal', 'http2', function($scope, $modal, http2) {
    $scope.years = [2014, 2015, 2016];
    $scope.months = [];
    $scope.days = [];
    $scope.hours = [];
    $scope.minutes = [];
    for (var i = 1; i <= 12; i++)
        $scope.months.push(i);
    for (var i = 1; i <= 31; i++)
        $scope.days.push(i);
    for (var i = 0; i <= 23; i++)
        $scope.hours.push(i);
    for (var i = 0; i <= 59; i++)
        $scope.minutes.push(i);
    $scope.updateTime = function(name) {
        var time = $scope[name].getTime();
        var p = {};
        p[name] = time / 1000;
        http2.post('/rest/mp/app/lottery/update?lid=' + $scope.lid, p);
    };
    $scope.update = function(name) {
        var p = {};
        p[name] = $scope.lottery[name];
        http2.post('/rest/mp/app/lottery/update?lid=' + $scope.lid, p);
    };
    $scope.setPic = function() {
        var options = {
            callback: function(url) {
                $scope.lottery.pic = url + '?_=' + (new Date()) * 1;
                $scope.update('pic');
            }
        };
        $scope.$broadcast('mediagallery.open', options);
    };
    $scope.removePic = function() {
        $scope.lottery.pic = '';
        $scope.update('pic');
    };
    $scope.setPage = function() {
        $modal.open({
            templateUrl: "pageSetting.html",
            controller: ['$scope', '$modalInstance', function($scope2, $mi) {
                $scope2.patterns = [{
                    l: '基本',
                    v: 'basic'
                }, {
                    l: '轮盘',
                    v: 'roulette'
                }, {
                    l: '摇一摇',
                    v: 'shake'
                }];
                $scope2.data = {};
                $scope2.close = function() {
                    $mi.dismiss();
                };
                $scope2.ok = function() {
                    $mi.close($scope2.data);
                }
            }],
        }).result.then(function(data) {
            http2.get('/rest/mp/app/lottery/pageSet?lid=' + $scope.lid + '&pageid=' + $scope.lottery.page_id + '&pattern=' + data.pattern.v, function(rsp) {
                $scope.gotoCode();
            });
        });
    };
    $scope.gotoCode = function() {
        if ($scope.lottery.page_id != 0)
            location.href = '/rest/code?pid=' + $scope.lottery.page_id;
        else {
            http2.get('/rest/code/create', function(rsp) {
                var nv = {
                    'page_id': rsp.data.id
                };
                http2.post('/rest/mp/app/lottery/update?lid=' + $scope.lid, nv, function() {
                    $scope.lottery.page_id = rsp.data.id;
                    location.href = '/rest/code?pid=' + rsp.data.id;
                });
            });
        }
    };
    $scope.$watch('lottery', function(lottery) {
        if (!lottery) return;
        var date;
        if (lottery.start_at == 0) {
            date = new Date();
            date.setTime(date.getTime());
        } else
            date = new Date(lottery.start_at * 1000);
        $scope.start_at = (function(date) {
            return {
                year: date.getFullYear(),
                month: date.getMonth() + 1,
                mday: date.getDate(),
                hour: date.getHours(),
                minute: date.getMinutes(),
                getTime: function() {
                    var d = new Date(this.year, this.month - 1, this.mday, this.hour, this.minute, 0, 0);
                    return d.getTime();
                }
            };
        })(date);
        if (lottery.end_at == 0) {
            date = new Date();
            date.setTime(date.getTime() + 86400000);
        } else
            date = new Date(lottery.end_at * 1000);
        $scope.end_at = (function(date) {
            return {
                year: date.getFullYear(),
                month: date.getMonth() + 1,
                mday: date.getDate(),
                hour: date.getHours(),
                minute: date.getMinutes(),
                getTime: function() {
                    var d = new Date(this.year, this.month - 1, this.mday, this.hour, this.minute, 0, 0);
                    return d.getTime();
                }
            };
        })(date);
    });
}]).controller('AwardCtrl', ['$scope', 'http2', '$rootScope', function($scope, http2, $rootScope) {
    $scope.addAward = function() {
        http2.get('/rest/mp/app/lottery/addAward?lid=' + $scope.lid + '&mpid=' + $scope.lottery.mpid, function(rsp) {
            $scope.lottery.awards.push(rsp.data);
        });
    };
    $scope.removeAward = function(award) {
        http2.get('/rest/mp/app/lottery/delAward?aid=' + award.aid, function(rsp) {
            var i = $scope.lottery.awards.indexOf(award);
            $scope.lottery.awards.splice(i, 1);
        });
    };
    $scope.setPic = function(award) {
        var options = {
            callback: function(url) {
                award.pic = url + '?_=' + (new Date()) * 1;
                $scope.update(award, 'pic');
            }
        };
        $scope.$broadcast('mediagallery.open', options);
    };
    $scope.removePic = function(award) {
        award.pic = '';
        $scope.update(award, 'pic');
    };
    $scope.update = function(award, name) {
        var p = {};
        p[name] = award[name];
        http2.post('/rest/mp/app/lottery/setAward?aid=' + award.aid, p);
    };
}]).controller('PlateCtrl', ['$scope', 'http2', function($scope, http2) {
    http2.get('/rest/mp/app/lottery/plate?lid=' + $scope.lid, function(rsp) {
        $scope.plate = rsp.data;
    });
    $scope.update = function(slot) {
        var p = {};
        p[slot] = $scope.plate[slot];
        http2.post('/rest/mp/app/lottery/setPlate?lid=' + $scope.lid, p);
    };
}])
.controller('resultCtrl', ['$rootScope', '$scope', 'http2', function($rootScope, $scope, http2) {
    var doSearch = function(page) {
        !page && (page = $scope.page.current);
        var url = '/rest/mp/app/lottery/log/list';
        url += '?lid=' + $scope.lid + '&page=' + page + '&size=' + $scope.page.size;
        url += '&startAt=' + $scope.startAt;
        url += '&endAt=' + $scope.endAt;
        if ($scope.byAward && $scope.byAward.length > 0)
            url += '&award=' + $scope.byAward;
        if ($scope.associatedAct)
            url += '&assocAct=' + $scope.associatedAct.aid;
        http2.get(url, function(rsp) {
            $scope.result = rsp.data.result;
            $scope.page.total = rsp.data.total;
            //rsp.data[2] && ($scope.assocDef = rsp.data[2]);
        });
    };
    var doStat = function() {
        http2.get('/rest/mp/app/lottery/stat?lid=' + $scope.lid, function(rsp) {
            $scope.stat = rsp.data;
        });
    };
    $scope.byAward = '';
    $scope.page = {
        current: 1,
        size: 30
    };
    var current, startAt, endAt;
    current = new Date();
    startAt = {
        year: current.getFullYear(),
        month: current.getMonth() + 1,
        mday: current.getDate(),
        getTime: function() {
            var d = new Date(this.year, this.month - 1, this.mday, 0, 0, 0, 0);
            return d.getTime();
        }
    };
    endAt = {
        year: current.getFullYear(),
        month: current.getMonth() + 1,
        mday: current.getDate(),
        getTime: function() {
            var d = new Date(this.year, this.month - 1, this.mday, 23, 59, 59, 0);
            return d.getTime();
        }
    };
    $scope.startAt = startAt.getTime() / 1000;
    $scope.endAt = endAt.getTime() / 1000;
    $scope.$on('xxt.tms-datepicker.change', function(n) {
        doSearch(1);
    });
    $scope.doSearch = function(page) {
        page ? doSearch(page) : doSearch();
    };
    $scope.viewUser = function(fan) {
        location.href = '/rest/mp/user?openid=' + fan.openid;
    };
    $scope.refresh = function() {
        doStat();
        doSearch();
    };
    $scope.removeRoll = function(r) {
        var vcode;
        vcode = prompt('是否要删除当前用户的所有抽奖记录？，若是，请输入活动名称。');
        if (vcode === $scope.lottery.title) {
            var url = '/rest/mp/app/lottery/removeRoll?lid=' + $scope.lid;
            if (r.openid && r.openid.length > 0)
                url += '&openid=' + r.openid;
            else
                url += '&mid=' + r.mid;
            http2.get(url, function(rsp) {
                $scope.refresh();
            });
        }
    };
    $scope.clean = function() {
        var vcode;
        vcode = prompt('是否要重新设置奖项数量，并删除所有抽奖记录？，若是，请输入活动名称。');
        if (vcode === $scope.lottery.title) {
            http2.get('/rest/mp/app/lottery/clean?lid=' + $scope.lid, function(rsp) {
                $scope.refresh();
            });
        }
    };
    $scope.addChance = function() {
        var vcode;
        vcode = prompt('是否要给未中奖用户增加1次抽奖机会？，若是，请输入活动名称。');
        if (vcode === $scope.lottery.title) {
            http2.get('/rest/mp/app/lottery/addChance?lid=' + $scope.lid, function(rsp) {
                $rootScope.infomsg = rsp.data;
            });
        }
    };
    $scope.refresh();
}]);