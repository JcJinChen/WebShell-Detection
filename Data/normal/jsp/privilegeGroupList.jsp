<%@ page pageEncoding="UTF-8" %>
<%@ include file="/page/share/taglib.jsp" %>
<html>
<head>
<title>权限组显示</title>
<link rel="stylesheet" href="/css/vip.css" type="text/css">
<script type="text/javascript" src="/js/jquery.js"></script>
	<script language="JavaScript">
<!--
	//到指定的分页页面
	function toPage(page){
		$("#currentPage").val(page);
		document.forms[0].submit();
	}
</script>
<SCRIPT language=JavaScript src="/js/FoshanRen.js"></SCRIPT>
</head>

<body bgcolor="#FFFFFF" text="#000000" marginwidth="0" marginheight="0">
<form action="/control/privilegegroup/list" method="post">
  <input id="currentPage" type="hidden" name="pageCtx.currentPage">
  <table width="98%" border="0" cellspacing="1" cellpadding="2" align="center">
    <tr ><td colspan="4" bgcolor="6f8ac4" align="right">
    	<%@ include file="/page/share/fenye.jsp" %>
   </td></tr>
    <tr>
      <td width="30%" bgcolor="6f8ac4"> <div align="center"><font color="#FFFFFF">代号</font></div></td>
      <td width="8%" nowrap bgcolor="6f8ac4"> <div align="center"><font color="#FFFFFF">修改</font></div></td>
      <td bgcolor="6f8ac4"> <div align="center"><font color="#FFFFFF">名称</font></div></td>
      <td width="10%" bgcolor="6f8ac4"></td>
    </tr>
<!---------------------------LOOP START------------------------------>
<c:forEach items="${privilegeGroups}" var="entry">
    <tr>
      <td bgcolor="f5f5f5"> <div align="center">${entry.groupId}</div></td>
      <td bgcolor="f5f5f5"> <div align="center">
      <c:if test="${entry.name != '系统权限组'}"><yc:permission privilege="update" module="privilegegroup">
      <a href="/control/privilegegroup/showUpdateUI?groupId=${entry.groupId}">
	  <img src="/images/edit.gif" width="15" height="16" border="0"></a>
	  </yc:permission></c:if></div></td>
      <td bgcolor="f5f5f5"> <div align="center">${entry.name}</div></td>
      <td bgcolor="f5f5f5"> <div align="center">
      <c:if test="${entry.name != '系统权限组'}"><yc:permission privilege="delete" module="privilegegroup">
      <a href="/control/privilegegroup/delete?groupId=${entry.groupId}">删除</a></yc:permission></c:if></div></td>
	</tr>
</c:forEach>
    <!----------------------LOOP END------------------------------->
    <tr> 
      <td bgcolor="f5f5f5" colspan="4" align="center"><table width="100%" border="0" cellspacing="1" cellpadding="3">
          <tr> 
            <td width="5%"></td>
              <td width="85%">
              <yc:permission privilege="add" module="privilegegroup">
              <input type="button" class="frm_btn" onClick="javascript:window.location.href='/control/privilegegroup/showAddUI'" value="添加权限组"> &nbsp;&nbsp;
              </yc:permission>
            </td>
          </tr>
        </table></td>
    </tr>
  </table>
</form>
</body>
</html>