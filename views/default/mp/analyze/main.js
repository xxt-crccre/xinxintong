xxtApp.config(['$routeProvider', function($routeProvider) {
    $routeProvider.when('/page/mp/analyze/mp', {
        templateUrl: '/views/default/mp/analyze/mp.html',
        controller: 'mpCtrl',
    }).when('/page/mp/analyze/user', {
        templateUrl: '/views/default/mp/analyze/user.html',
        controller: 'userCtrl',
    }).when('/page/mp/analyze/matter', {
        templateUrl: '/views/default/mp/analyze/matter.html',
        controller: 'matterCtrl'
    }).otherwise({
        templateUrl: '/views/default/mp/analyze/mp.html',
        controller: 'mpCtrl'
    });
}]);
xxtApp.controller('analyzeCtrl', ['$scope', function($scope) {
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
    $scope.open = function($event) {
        $event.preventDefault();
        $event.stopPropagation();
        $scope.opened = true;
    };
    $scope.subView = '';
}]);
xxtApp.controller('mpCtrl', ['$scope', 'http2', function($scope, http2) {
    $scope.$parent.subView = 'mp';
    $scope.page = {
        size: 30
    };
    $scope.getData = function(page) {
        if (page) $scope.page.current = page;
        var url = '/rest/mp/analyze/mpActions';
        url += '?startAt=' + $scope.startAt;
        url += '&endAt=' + $scope.endAt;
        http2.get(url, function(rsp) {
            $scope.logs = rsp.data[0];
            $scope.page.total = rsp.data[1];
        });
    };
    $scope.$on('xxt.tms-datepicker.change', function(event, data) {
        $scope[data.state] = data.value;
        $scope.getData(1);
    });
    $scope.getData(1);
}]);
xxtApp.controller('userCtrl', ['$scope', 'http2', function($scope, http2) {
    $scope.$parent.subView = 'user';
    $scope.page = {
        size: 30
    };
    $scope.orderby = 'read';
    $scope.getData = function(page) {
        if (page) $scope.page.current = page;
        var url = '/rest/mp/analyze/userActions';
        url += '?orderby=' + $scope.orderby;
        url += '&startAt=' + $scope.startAt;
        url += '&endAt=' + $scope.endAt;
        url += '&page=' + $scope.page.current;
        url += '&size=' + $scope.page.size;
        http2.get(url, function(rsp) {
            $scope.users = rsp.data[0];
            $scope.page.total = rsp.data[1];
        });
    };
    $scope.viewUser = function(openid) {
        location.href = '/rest/mp/user?openid=' + openid;
    };
    $scope.$on('xxt.tms-datepicker.change', function(event, data) {
        $scope[data.state] = data.value;
        $scope.getData(1);
    });
    $scope.getData(1);
}]);
xxtApp.controller('matterCtrl', ['$scope', 'http2', function($scope, http2) {
    $scope.$parent.subView = 'matter';
    $scope.page = {
        size: 30
    };
    $scope.orderby = 'read';
    $scope.getData = function(page) {
        if (page) $scope.page.current = page;
        var url = '/rest/mp/analyze/matterActions';
        url += '?orderby=' + $scope.orderby;
        url += '&startAt=' + $scope.startAt;
        url += '&endAt=' + $scope.endAt;
        url += '&page=' + $scope.page.current;
        url += '&size=' + $scope.page.size;
        http2.get(url, function(rsp) {
            $scope.matters = rsp.data[0];
            $scope.page.total = rsp.data[1];
        });
    };
    $scope.$on('xxt.tms-datepicker.change', function(event, data) {
        $scope[data.state] = data.value;
        $scope.getData(1);
    });
    $scope.getData(1);
}]);